<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Http\Controllers\Admin\Concerns\SortsAdminList;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Quản lý khách hàng và quản trị viên.
 * ============================================================
 * BA VIỆC, và ranh giới giữa chúng là chủ ý chứ không phải dở dang:
 *
 *   - XEM & TÌM: thứ dùng hằng ngày — tìm đúng một khách khi họ gọi
 *     điện, và biết họ đã mua bao nhiêu.
 *
 *   - ĐỔI VAI TRÒ: có, kèm ba chốt chặn để cửa hàng không tự khoá mình
 *     ở ngoài khu quản trị. Xem updateRole().
 *
 *   - KHOÁ / MỞ KHOÁ: có. Chặn được người mà giữ nguyên mọi bản ghi.
 *     Xem updateLock() và middleware EnsureUserIsNotLocked.
 *
 * VẪN KHÔNG CÓ XOÁ TÀI KHOẢN, và đó là quyết định chứ không phải phần
 * còn thiếu: đơn hàng đã hoàn tất PHẢI giữ lại làm chứng từ (xem trang
 * Chính sách bảo mật), nên "xoá" thật ra là gỡ liên kết — mất người
 * đứng tên trên doanh thu cũ, mất tên dưới các đánh giá. Gần như mọi
 * lần người ta muốn xoá một tài khoản, thứ họ thật sự cần là KHOÁ nó.
 */
class UserController extends Controller
{
    use LogsAdminActivity;
    use SortsAdminList;

    public function index(Request $request): View
    {
        $users = User::query()
            /*
             * TÌM THEO TÊN HOẶC EMAIL.
             *
             * Không tìm theo số điện thoại: bảng `users` KHÔNG có cột đó
             * — số điện thoại nằm ở `addresses` và trên từng đơn hàng.
             * Muốn tìm theo số thì tìm ở trang Đơn hàng, nơi con số đó
             * thật sự sống.
             */
            ->when($request->filled('q'), function ($query) use ($request) {
                $tu = trim((string) $request->query('q'));

                $query->where(function ($q) use ($tu) {
                    $q->where('name', 'like', '%'.$tu.'%')
                        ->orWhere('email', 'like', '%'.$tu.'%');
                });
            })

            ->when(
                $request->filled('role'),
                fn ($q) => $q->where('role', $request->string('role'))
            )

            /*
             * LỌC THEO TÌNH TRẠNG XÁC THỰC EMAIL.
             *
             * Có ích thật: tài khoản chưa xác thực không dùng được ví
             * voucher và lịch sử đơn, nên khi khách gọi kêu "không vào
             * được mục của tôi" thì đây là chỗ nhìn đầu tiên.
             */
            ->when(
                $request->query('xac_thuc') === 'chua',
                fn ($q) => $q->whereNull('email_verified_at')
            )
            ->when(
                $request->query('xac_thuc') === 'roi',
                fn ($q) => $q->whereNotNull('email_verified_at')
            )

            /*
             * Số đơn ĐÃ HOÀN THÀNH và tổng tiền đã mua.
             *
             * withCount/withSum chứ không nạp cả quan hệ orders: danh
             * sách chỉ cần con số, nạp hết đơn của từng người là N+1.
             *
             * Chỉ tính đơn `completed` — đơn đang chờ hoặc đã huỷ không
             * phải tiền cửa hàng đã nhận, đưa vào là con số nói dối.
             */
            ->withCount(['orders as completed_orders_count' => fn ($q) => $q->where('status', 'completed')])
            ->withSum(['orders as spent_total' => fn ($q) => $q->where('status', 'completed')], 'grand_total')

            ->tap(fn ($q) => $this->applySort($q, $request, [
                'ten' => 'name',
                'email' => 'email',
                /*
                 * Sắp được theo hai cột do withCount/withSum sinh ra:
                 * "ai mua nhiều nhất" là câu hỏi thật của người bán, và
                 * bí danh trong SQL thì orderBy dùng được như cột thường.
                 */
                'so-don' => 'completed_orders_count',
                'da-chi' => 'spent_total',
                'ngay' => 'created_at',
            ], fn ($q) => $q->latest()))

            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => UserRole::cases(),
        ]);
    }

    /**
     * Đổi vai trò của một tài khoản.
     *
     * BA CHỐT CHẶN, và cả ba đều bảo vệ trước cùng một tai nạn: cửa hàng
     * mất quyền vào chính khu quản trị của mình.
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ], [], ['role' => 'vai trò']);

        $moi = UserRole::from($data['role']);
        $cu = $user->role;

        if ($moi === $cu) {
            return back()->with('info', 'Vai trò không thay đổi.');
        }

        /*
         * CHỐT 1 — KHÔNG TỰ ĐỔI VAI TRÒ CỦA CHÍNH MÌNH.
         *
         * Admin duy nhất tự hạ mình xuống khách hàng là mất quyền vào
         * khu quản trị, và không còn ai mở lại được ngoài việc sửa thẳng
         * cơ sở dữ liệu. Đây là loại lỗi chỉ xảy ra một lần trong đời và
         * mất cả buổi để chữa.
         *
         * Chặn cả chiều tự NÂNG quyền cho mình luôn: một admin thì không
         * cần, còn nếu sau này có vai trò thấp hơn thì đó chính là lỗ
         * hổng leo thang quyền.
         */
        if ($user->is(Auth::user())) {
            return back()->with(
                'error',
                'Không tự đổi vai trò của chính mình được. Hãy nhờ một quản trị viên khác.',
            );
        }

        /*
         * CHỐT 2 — LUÔN CÒN ÍT NHẤT MỘT QUẢN TRỊ VIÊN DÙNG ĐƯỢC.
         *
         * Đếm số admin còn lại NẾU thao tác này đi qua — tức là đếm
         * những admin chưa bị khoá, TRỪ chính người đang bị tác động.
         *
         * Đếm kiểu "tổng số admin dùng được <= 1" thì chặn nhầm: khi có
         * một admin đang bị khoá, hạ quyền người đó chẳng làm ai mất
         * quyền truy cập cả, nhưng phép đếm ấy vẫn từ chối.
         *
         * Không tính admin đang bị khoá vào phần "còn lại": họ không
         * đăng nhập được, nên coi họ là lối vào dự phòng là tự lừa mình
         * đúng vào lúc phép đếm này cần chính xác nhất.
         *
         * Ở cấu hình quyền hiện tại, chốt này gần như không bao giờ chạm
         * tới: người thao tác bắt buộc là một admin dùng được và không
         * tự đổi được vai trò của mình, nên luôn còn ít nhất một người.
         * Giữ lại vì nó sẽ có việc ngay khi xuất hiện vai trò thứ ba
         * (nhân viên) được phép vào màn hình này.
         */
        if ($cu === UserRole::Admin && $moi !== UserRole::Admin && $this->adminConLaiNeuBo($user) === 0) {
            return back()->with(
                'error',
                'Đây là quản trị viên duy nhất còn hoạt động. '
                .'Hãy chỉ định một quản trị viên khác trước khi hạ quyền tài khoản này.',
            );
        }

        $user->role = $moi;
        $user->save();

        /*
         * CHỐT 3 — GHI NHẬT KÝ.
         *
         * Không phải phép chặn, nhưng cùng một mục đích: trao quyền quản
         * trị là thao tác có hậu quả lớn nhất ở màn hình này, và "ai
         * phong admin cho tài khoản kia" phải trả lời được sau nhiều
         * tháng.
         */
        $this->audit()->logChange(
            'user.role_changed',
            $user,
            'Vai trò của '.$user->name,
            $cu->label(),
            $moi->label(),
            ['email' => $user->email],
        );

        return back()->with(
            'success',
            sprintf('Đã đổi vai trò của %s thành "%s".', $user->name, $moi->label()),
        );
    }

    /**
     * Khoá hoặc mở khoá một tài khoản.
     *
     * KHOÁ, KHÔNG XOÁ. Xoá tài khoản làm đơn hàng cũ mất người đứng tên
     * và đánh giá thành "(tài khoản đã xoá)" — mất dữ liệu thật để xử lý
     * một vấn đề về hành vi. Khoá thì chặn được người mà giữ nguyên mọi
     * bản ghi, và mở lại chỉ là gỡ một dấu thời gian.
     */
    public function updateLock(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'khoa' => ['required', 'boolean'],
            'ly_do' => ['nullable', 'string', 'max:255'],
        ], [], ['ly_do' => 'lý do']);

        $khoa = (bool) $data['khoa'];

        if ($khoa === $user->isLocked()) {
            return back()->with('info', 'Trạng thái tài khoản không thay đổi.');
        }

        /*
         * KHÔNG TỰ KHOÁ CHÍNH MÌNH.
         *
         * Middleware EnsureUserIsNotLocked chạy ở mọi request, nên tự
         * khoá là bị đá ra ngay ở lần bấm kế tiếp — và không vào lại
         * được để mở khoá. Không có màn hình nào chữa được việc đó.
         */
        if ($khoa && $user->is(Auth::user())) {
            return back()->with('error', 'Không tự khoá tài khoản của chính mình được.');
        }

        // Cùng lý do với chốt 2 ở updateRole: khoá nốt admin dùng được
        // cuối cùng là khoá cửa chính mình ở ngoài.
        if ($khoa && $user->isAdmin() && $this->adminConLaiNeuBo($user) === 0) {
            return back()->with(
                'error',
                'Đây là quản trị viên duy nhất còn hoạt động, không khoá được.',
            );
        }

        $user->locked_at = $khoa ? now() : null;

        /*
         * Mở khoá thì XOÁ luôn lý do.
         *
         * Giữ lại thì lần khoá sau — nếu ai đó quên nhập lý do mới —
         * người dùng sẽ đọc được lời giải thích của lần trước, cho một
         * việc hoàn toàn khác.
         */
        $user->lock_reason = $khoa ? ($data['ly_do'] ?? null) : null;
        $user->save();

        $this->audit()->log(
            $khoa ? 'user.locked' : 'user.unlocked',
            sprintf('%s tài khoản %s', $khoa ? 'Khoá' : 'Mở khoá', $user->name),
            $user,
            array_filter([
                'email' => $user->email,
                'ly_do' => $khoa ? ($data['ly_do'] ?? null) : null,
            ]),
        );

        return back()->with('success', $khoa
            ? 'Đã khoá tài khoản '.$user->name.'. Họ sẽ bị đăng xuất ngay.'
            : 'Đã mở khoá tài khoản '.$user->name.'.');
    }

    /**
     * Còn bao nhiêu quản trị viên đăng nhập được, NẾU bỏ người này ra.
     *
     * Trả lời đúng câu hỏi cần hỏi trước khi hạ quyền hoặc khoá ai đó:
     * "làm việc này xong thì còn ai vào được khu quản trị không?".
     *
     * whereKeyNot loại chính người đang bị tác động, và whereNull
     * ('locked_at') loại những admin đang bị khoá — họ không đăng nhập
     * được nên không phải lối vào dự phòng.
     */
    private function adminConLaiNeuBo(User $user): int
    {
        return User::where('role', UserRole::Admin)
            ->whereNull('locked_at')
            ->whereKeyNot($user->getKey())
            ->count();
    }
}
