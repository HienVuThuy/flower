<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\ChangePasswordRequest;
use App\Http\Requests\Shop\ProfileRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\Auth\AccountDeleter;
use App\Services\Auth\AccountDeletionException;
use App\Services\Auth\AccountSecurity;
use App\Services\Auth\ActiveSessions;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Hồ sơ tài khoản của khách (Guide §11 — "User → Hồ sơ").
 * ============================================================
 * Đặt trong Shop/ chứ không phải Auth/: đây là khu vực tài khoản của
 * khách, cùng nhóm với Sổ địa chỉ và Đơn hàng của tôi. Auth/ dành cho
 * việc ra vào hệ thống (đăng nhập, đăng ký, đặt lại mật khẩu).
 *
 * HAI BIỂU MẪU TÁCH RIÊNG, KHÔNG GỘP LÀM MỘT:
 * Đổi tên và đổi mật khẩu có ràng buộc bảo mật khác hẳn nhau. Gộp chung
 * một biểu mẫu thì hoặc phải bắt nhập mật khẩu cho cả việc sửa tên (phiền
 * vô ích), hoặc để lọt việc đổi mật khẩu mà không cần xác thực. Tách ra
 * thì mỗi việc giữ đúng mức bảo vệ của nó.
 */
class ProfileController extends Controller
{
    public function __construct(
        private readonly AccountSecurity $security,
        private readonly ActiveSessions $sessions,
        private readonly AccountDeleter $deleter,
    ) {
    }

    /**
     * Các mục của trang hồ sơ, theo thứ tự hiện trên thanh điều hướng.
     *
     * KHAI Ở ĐÂY, KHÔNG KHAI TRONG BLADE. Vừa là danh sách trắng cho
     * tham số `?muc=` — giá trị lạ quy về mục đầu tiên chứ không làm
     * hỏng trang — vừa là nguồn duy nhất dựng ra thanh điều hướng, nên
     * thêm một mục là sửa đúng một chỗ.
     */
    private const MUC = [
        'thong-tin' => 'Thông tin',
        'diem-thuong' => 'Điểm thưởng',
        'bao-mat' => 'Bảo mật',
        'tuy-chon' => 'Tuỳ chọn',
    ];

    public function edit(Request $request): View
    {
        $user = Auth::user();

        /*
         * Tham số lạ thì về mục mặc định, KHÔNG báo lỗi.
         *
         * `?muc=abc` chỉ tới được bằng cách tự gõ hoặc bằng một đường
         * dẫn cũ đã đổi tên. Cả hai đều không đáng để đổ trang lỗi —
         * hiện mục đầu tiên là điều người dùng chờ đợi.
         */
        $muc = (string) $request->query('muc');

        if (! array_key_exists($muc, self::MUC)) {
            $muc = array_key_first(self::MUC);
        }

        return view('shop.profile.edit', [
            'muc' => $muc,
            'cacMuc' => self::MUC,
            'user' => $user,

            /*
             * Danh sách thiết bị đang đăng nhập.
             *
             * Truyền id phiên hiện tại xuống để đánh dấu "thiết bị này" —
             * thiếu dấu đó thì khách nhìn ba dòng giống hệt nhau và không
             * dám bấm đá dòng nào, vì sợ tự đá chính mình.
             */
            'sessions' => $this->sessions->forUser($user, $request->session()->getId()),
            'sessionsSupported' => $this->sessions->supported(),

            /*
             * Vài con số cho khách tự đối chiếu. ĐẾM THẬT từ cơ sở dữ
             * liệu, không ước lượng — con số sai còn tệ hơn không có số.
             * withCount thay vì nạp cả quan hệ: chỉ cần con số.
             */
            'orderCount' => Order::where('user_id', $user->id)->count(),
            'wishlistCount' => $user->wishlists()->count(),
            'reviewCount' => $user->reviews()->count(),

            // Sổ điểm chỉ đọc khi mở đúng mục đó.
            'diem' => $muc === 'diem-thuong' ? [
                'so_du' => app(\App\Services\Points\PointLedger::class)->soDu($user),
                'lich_su' => app(\App\Services\Points\PointLedger::class)->lichSu($user),
                'goi' => \App\Services\Points\PointLedger::GOI,
                'chuoi' => app(\App\Services\Points\VisitStreak::class)->hienTai($user),
            ] : null,
        ]);
    }

    /* ============ THÔNG TIN CÁ NHÂN ============ */

    public function update(ProfileRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $data = $request->validated();

        $emailChanged = $data['email'] !== $user->email;

        /*
         * Gán từng trường, KHÔNG dùng ->update($data).
         *
         * $data còn chứa `current_password` — một trường chỉ dùng để xác
         * thực, không phải dữ liệu hồ sơ. Đổ cả mảng vào model là trông
         * chờ $fillable chặn hộ; gán tay thì nhìn là biết cột nào đổi.
         */
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->save();

        if ($emailChanged) {
            /*
             * Đổi email là đổi luôn tên đăng nhập. Ghi log để còn lần ra
             * được khi có tranh chấp — chỉ ghi id, không ghi địa chỉ.
             */
            Log::info('Người dùng đổi địa chỉ email.', ['user_id' => $user->id]);
        }

        return back()->with('success', $emailChanged
            ? 'Đã cập nhật thông tin. Lần đăng nhập sau hãy dùng email mới.'
            : 'Đã cập nhật thông tin tài khoản.');
    }

    /* ============ ĐỔI MẬT KHẨU ============ */

    public function updatePassword(ChangePasswordRequest $request): RedirectResponse
    {
        $user = Auth::user();

        /*
         * Giữ lại ĐÚNG phiên đang thao tác, huỷ mọi phiên khác.
         *
         * Lấy id phiên TRƯỚC khi đổi mật khẩu: sau đó ta còn regenerate
         * nên id sẽ khác, và nếu lấy sau thì hoá ra lại xoá nhầm chính
         * phiên vừa được giữ.
         */
        $currentSessionId = $request->session()->getId();

        $killed = $this->security->changePassword(
            $user,
            $request->validated('password'),
            $currentSessionId,
            // Địa chỉ IP đi vào thư cảnh báo, giúp chủ tài khoản nhận ra
            // ngay đây có phải máy của mình hay không.
            $request->ip(),
        );

        /*
         * Đổi id phiên hiện tại sau khi đổi mật khẩu.
         *
         * Nếu kẻ tấn công từng biết id phiên này (session fixation), đổi
         * mật khẩu xong mà giữ nguyên id thì họ vẫn bám được vào phiên.
         *
         * THAM SỐ `true` LÀ BẮT BUỘC, KHÔNG PHẢI TUỲ CHỌN.
         *
         * regenerate() mặc định là regenerate(false): nó cấp id mới nhưng
         * ĐỂ NGUYÊN bản ghi phiên cũ trong bảng `sessions`, và bản ghi đó
         * vẫn còn nguyên dữ liệu đăng nhập. Đo trực tiếp: sau khi đổi mật
         * khẩu còn HAI hàng phiên, cả hai đều chứa khoá `login_web_*` —
         * tức id cũ vẫn vào được, đúng cái mà đoạn này tưởng đã chặn.
         *
         * `true` xoá hẳn bản ghi cũ trước khi cấp id mới.
         */
        $request->session()->regenerate(true);

        Log::info('Người dùng đổi mật khẩu.', [
            'user_id' => $user->id,
            'sessions_killed' => $killed,
        ]);

        return back()->with('success', $killed > 0
            ? sprintf('Đã đổi mật khẩu. %d phiên đăng nhập trên thiết bị khác đã bị đăng xuất.', $killed)
            : 'Đã đổi mật khẩu.');
    }

    /**
     * Gửi liên kết đặt lại mật khẩu về CHÍNH email của người đang đăng nhập.
     *
     * VẤN ĐỀ NÓ GIẢI QUYẾT: biểu mẫu đổi mật khẩu bắt nhập mật khẩu hiện
     * tại — đúng, vì không có phép kiểm đó thì ai mượn được máy đang mở
     * sẵn cũng chiếm được tài khoản. Nhưng người đăng nhập bằng "ghi nhớ
     * đăng nhập" từ nhiều tháng trước thì hoàn toàn có thể **không còn
     * nhớ mật khẩu cũ**, và khi ấy họ mắc kẹt: đang đăng nhập mà không
     * đổi được mật khẩu.
     *
     * Đường thoát duy nhất trước đây là đăng xuất rồi bấm "Quên mật
     * khẩu" — mà trang đó nằm sau middleware `guest`, nên phải đăng xuất
     * THẬT rồi mới vào được. Không ai đoán ra bước đó.
     *
     * KHÔNG GỬI CHO ĐỊA CHỈ DO BIỂU MẪU GỬI LÊN. Địa chỉ lấy từ tài
     * khoản đang đăng nhập, phía máy chủ. Nhận email từ request thì màn
     * hình này thành công cụ gửi thư tới địa chỉ bất kỳ, ký tên cửa hàng.
     *
     * DÙNG LẠI ĐÚNG CƠ CHẾ CỦA TRANG "QUÊN MẬT KHẨU", không tự dựng mã
     * OTP riêng: hai đường đặt lại mật khẩu là hai bộ luật phải giữ đồng
     * bộ về hạn dùng, số lần thử và cách huỷ token — và bộ thứ hai sẽ là
     * bộ bị quên khi có gì đó cần sửa.
     */
    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            Log::warning('Không gửi được liên kết đặt lại mật khẩu từ trang hồ sơ.', [
                'user_id' => $user->id,
                'status' => $status,
            ]);

            return back()->with(
                'error',
                'Chưa gửi được thư lúc này. Vui lòng thử lại sau ít phút.',
            );
        }

        /*
         * Ở ĐÂY NÓI THẲNG ĐỊA CHỈ, khác với trang "Quên mật khẩu".
         *
         * Trang kia phải trả lời trung tính để không thành công cụ dò
         * xem email nào đã đăng ký. Ở đây người dùng đã đăng nhập và
         * đang xem chính hồ sơ của mình — họ không dò ra thông tin nào
         * mà họ chưa có. Giấu địa chỉ lúc này chỉ khiến họ không biết
         * phải mở hộp thư nào.
         */
        return back()->with(
            'success',
            'Đã gửi liên kết đặt lại mật khẩu tới '.$user->email
            .'. Liên kết có hạn 60 phút.',
        );
    }

    /* ============ TUỲ CHỌN NHẬN THƯ ============ */

    public function updateNotifications(Request $request): RedirectResponse
    {
        $user = Auth::user();

        /*
         * Ô đánh dấu KHÔNG được gửi lên khi bỏ tích — đó là cách HTML
         * hoạt động, không phải lỗi. Vì thế phải đọc bằng `boolean()`
         * (thiếu khoá = false) chứ không phải validate "required".
         */
        $user->notify_order_updates = $request->boolean('notify_order_updates');

        /*
         * Công tắc thứ hai, TÁCH RIÊNG khỏi thư đơn hàng.
         *
         * Hai loại thư khác hẳn nhau: thư đơn hàng là việc mua bán, thư
         * nhắc chăm cây là dịch vụ sau bán. Có người muốn cái này mà
         * không muốn cái kia — gộp một công tắc là bắt họ chọn cả cụm.
         */
        $user->notify_care_reminders = $request->boolean('notify_care_reminders');

        $user->save();

        return back()->with('success', 'Đã lưu tuỳ chọn nhận thư.');
    }

    /* ============ THIẾT BỊ ĐANG ĐĂNG NHẬP ============ */

    public function revokeSession(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $sessionId = (string) $request->input('session_id');
        $currentSessionId = $request->session()->getId();

        /*
         * Chặn tự đá chính mình ở đây.
         *
         * Xoá bản ghi phiên đang dùng thì khách bị đăng xuất ngay lập tức
         * và bị ném về trang đăng nhập kèm một thông báo mà họ sẽ không
         * bao giờ đọc được. Muốn thoát ở thiết bị này thì đã có nút Đăng
         * xuất — đúng công cụ cho đúng việc.
         */
        if ($sessionId === $currentSessionId) {
            return back()->with('error', 'Đây là thiết bị bạn đang dùng. Hãy dùng nút Đăng xuất.');
        }

        $revoked = $this->sessions->revoke($user, $sessionId);

        if (! $revoked) {
            /*
             * Không tìm thấy phiên. Hai khả năng: id bị sửa, hoặc phiên
             * vừa tự hết hạn giữa lúc khách đang xem trang. Trả về cùng
             * một câu cho cả hai — phân biệt ra chỉ giúp người dò id biết
             * mình đoán đúng hay sai.
             */
            return back()->with('error', 'Không tìm thấy phiên đăng nhập đó. Có thể nó đã hết hạn.');
        }

        Log::info('Người dùng đá một phiên đăng nhập.', ['user_id' => $user->id]);

        return back()->with('success', 'Đã đăng xuất thiết bị đó.');
    }

    /* ============ XOÁ TÀI KHOẢN ============ */

    /**
     * BƯỚC 1 — gửi thư xác nhận.
     *
     * Không xoá gì ở bước này. Cả ba bước cố ý tách rời nhau:
     *
     *   1. bấm nút            → gửi thư (chứng minh đọc được hộp thư)
     *   2. bấm liên kết       → mở trang xác nhận, KHÔNG xoá
     *   3. gõ đúng một dòng   → mới thật sự xoá
     *
     * Gộp bất kỳ hai bước nào cũng làm mất một lớp bảo vệ. Gộp 1 và 2
     * thì bấm một nút là mất tài khoản; gộp 2 và 3 thì phần xem trước
     * liên kết của Gmail cũng xoá được.
     */
    public function requestDeletion(Request $request): RedirectResponse
    {
        try {
            $this->deleter->sendConfirmation(Auth::user());
        } catch (AccountDeletionException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Không gửi được thư xác nhận xoá tài khoản.', [
                'user_id' => Auth::id(),
                'exception' => $e->getMessage(),
            ]);

            return back()->with('error', 'Chưa gửi được thư lúc này. Vui lòng thử lại sau ít phút.');
        }

        return back()->with(
            'success',
            'Đã gửi thư xác nhận tới '.Auth::user()->email
            .'. Mở thư và bấm liên kết trong đó để tiếp tục — liên kết có hạn '
            .AccountDeleter::TTL_MINUTES.' phút.',
        );
    }

    /**
     * BƯỚC 2 — trang xác nhận, mở từ liên kết trong thư.
     *
     * Route có middleware `signed`, nên tới được đây nghĩa là chữ ký còn
     * hợp lệ và chưa hết hạn.
     *
     * VẪN KIỂM NGƯỜI ĐANG ĐĂNG NHẬP CÓ ĐÚNG LÀ CHỦ LIÊN KẾT KHÔNG.
     * Chữ ký chứng minh liên kết do máy chủ phát ra, KHÔNG chứng minh
     * người đang cầm nó là chủ tài khoản: liên kết có thể bị chuyển
     * tiếp, dán vào nhóm chat, hoặc lọt vào lịch sử trình duyệt máy
     * chung. Thiếu phép kiểm này thì ai cầm được liên kết cũng xoá được
     * tài khoản người khác.
     */
    public function confirmDeletion(Request $request, User $user): View|RedirectResponse
    {
        if (! $user->is(Auth::user())) {
            return redirect()
                ->route('shop.profile.edit')
                ->with('error', 'Liên kết này thuộc về một tài khoản khác.');
        }

        return view('shop.profile.delete', [
            'user' => $user,
            'summary' => $this->deleter->summary($user),
            'cauXacNhan' => AccountDeleter::CAU_XAC_NHAN,
            // Chuyền tiếp chữ ký sang biểu mẫu POST: bước xoá thật cũng
            // đứng sau `signed`, nên nó cần đúng bộ tham số đã ký.
            'signedQuery' => $request->getQueryString(),
        ]);
    }

    /**
     * BƯỚC 3 — xoá thật.
     *
     * Bắt gõ đúng một dòng, KHÔNG chỉ bấm nút "Đồng ý".
     *
     * Hộp thoại xác nhận thì người ta bấm theo phản xạ — đó là cú bấm
     * thứ hai trong cùng một nhịp tay. Phải gõ một dòng thì buộc dừng
     * lại, đọc, và làm một việc khác hẳn. Với thao tác không có đường
     * lùi thì cái khựng lại đó chính là thứ cần.
     */
    public function destroyAccount(Request $request, User $user): RedirectResponse
    {
        if (! $user->is(Auth::user())) {
            return redirect()
                ->route('shop.profile.edit')
                ->with('error', 'Liên kết này thuộc về một tài khoản khác.');
        }

        $request->validate([
            'xac_nhan' => ['required', 'string', 'in:'.AccountDeleter::CAU_XAC_NHAN],
        ], [
            'xac_nhan.required' => 'Hãy gõ đúng dòng xác nhận để tiếp tục.',
            'xac_nhan.in' => 'Dòng xác nhận chưa đúng. Hãy gõ chính xác: '
                .AccountDeleter::CAU_XAC_NHAN,
        ]);

        try {
            $this->deleter->delete($user);
        } catch (AccountDeletionException $e) {
            return back()->with('error', $e->getMessage());
        }

        /*
         * Huỷ phiên SAU khi xoá xong, ở tầng controller.
         *
         * AccountDeleter đã gọi Auth::logout() và dọn bảng `sessions`,
         * nhưng phiên hiện tại vẫn còn dữ liệu trong bộ nhớ của request
         * này — giỏ hàng, vé xem đơn của khách vãng lai. Không dọn thì
         * người vừa xoá tài khoản đi tiếp với giỏ hàng cũ.
         */
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('welcome')
            ->with('success', 'Tài khoản của bạn đã được xoá. Cảm ơn bạn đã ghé ' . \App\Services\Shop\StoreProfile::name() . '.');
    }
}