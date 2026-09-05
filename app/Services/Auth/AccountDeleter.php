<?php

namespace App\Services\Auth;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Mail\AccountDeletionMail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Xoá tài khoản theo yêu cầu của chính chủ.
 * ============================================================
 * NƠI DUY NHẤT quyết định ai được xoá và xoá thì mất những gì.
 *
 * XÁC MINH BẰNG LIÊN KẾT KÝ SỐ GỬI QUA EMAIL, KHÔNG BẰNG MÃ OTP RIÊNG.
 *
 * Mã OTP cần một bảng riêng cùng toàn bộ luật đi kèm: hạn dùng, đếm số
 * lần gõ sai, thời gian chờ gửi lại, băm mã. Hệ thống ĐÃ có đúng bộ luật
 * đó trong EmailVerifier — viết bản thứ hai là hai bộ luật bảo mật phải
 * giữ đồng bộ, và bản thứ hai sẽ là bản bị quên khi có gì cần sửa.
 *
 * `URL::temporarySignedRoute` cho đúng ba thứ cần thiết — chứng minh
 * người bấm đọc được hộp thư, có hạn dùng, không sửa được — mà không
 * thêm bảng nào và không thêm một dòng mã bảo mật tự viết nào.
 *
 * LIÊN KẾT KHÔNG XOÁ GÌ CẢ. Nó chỉ mở một trang xác nhận, ở đó người
 * dùng phải tự gõ một dòng rồi gửi biểu mẫu POST. Nếu bản thân liên kết
 * xoá được thì phần xem trước liên kết của Gmail, phần quét virus của
 * doanh nghiệp, hay một cú bấm nhầm cũng xoá được tài khoản.
 */
class AccountDeleter
{
    /**
     * Hạn của liên kết, tính bằng phút.
     *
     * Ngắn hơn hẳn liên kết đặt lại mật khẩu (60 phút) vì hậu quả nặng
     * hơn hẳn và không có đường lùi: đặt lại mật khẩu sai thì đặt lại
     * lần nữa, xoá tài khoản sai thì không lấy lại được.
     */
    public const TTL_MINUTES = 30;

    /** Dòng người dùng phải gõ để xác nhận. */
    public const CAU_XAC_NHAN = 'XOA TAI KHOAN';

    /**
     * Gửi thư xác nhận tới chính email của tài khoản.
     *
     * @throws AccountDeletionException khi tài khoản này chưa được phép xoá
     */
    public function sendConfirmation(User $user): void
    {
        $this->assertDeletable($user);

        $url = URL::temporarySignedRoute(
            'shop.profile.delete.confirm',
            now()->addMinutes(self::TTL_MINUTES),
            ['user' => $user->id],
        );

        Mail::to($user->email)->send(
            new AccountDeletionMail($user, $url, self::TTL_MINUTES)
        );

        Log::info('Đã gửi thư xác nhận xoá tài khoản.', ['user_id' => $user->id]);
    }

    /**
     * Xoá thật.
     *
     * KHOÁ NGOẠI LO PHẦN DỌN DẸP, không tự đi xoá từng bảng ở đây. Mỗi
     * bảng đã khai sẵn ý định của nó trong migration, và chép lại ý định
     * đó vào PHP là tạo bản sao thứ hai sẽ lệch: thêm một bảng mới mà
     * quên sửa hàm này thì dữ liệu cá nhân ở lại mà không ai biết.
     *
     *   CASCADE (đi theo tài khoản) : addresses, wishlists, reviews,
     *                                 care_reminders, coupon_user,
     *                                 email_verification_codes
     *   SET NULL (ở lại, mất chủ)   : orders, activity_logs, user_events,
     *                                 carts, bulk_order_inquiries
     *
     * Đơn hàng ở lại là CÓ CHỦ Ý, và đã ghi trong trang Chính sách bảo
     * mật: đó là chứng từ mua bán, cửa hàng phải giữ để đối soát. Đơn
     * vẫn giao được vì tên, số điện thoại và địa chỉ người nhận là bản
     * CHỤP nằm ngay trên chính đơn, không đọc sang bảng users.
     *
     * @throws AccountDeletionException
     */
    public function delete(User $user): void
    {
        $this->assertDeletable($user);

        DB::transaction(function () use ($user) {
            /*
             * Đăng xuất TRƯỚC khi xoá, trong cùng transaction.
             *
             * Xoá trước rồi mới đăng xuất thì giữa hai bước đó phiên vẫn
             * trỏ tới một user_id không còn tồn tại — mọi truy vấn nạp
             * người dùng hiện tại sẽ trả về null, và trang tiếp theo đổ
             * lỗi thay vì hiện lời chào tạm biệt.
             */
            if (Auth::check() && Auth::id() === $user->id) {
                Auth::guard('web')->logout();
            }

            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();

            $user->delete();
        });

        Log::info('Tài khoản đã bị xoá theo yêu cầu của chủ tài khoản.');
    }

    /**
     * Những gì sẽ mất và những gì ở lại — để màn hình xác nhận nói ĐÚNG
     * con số, không nói chung chung.
     *
     * "Bạn sẽ mất dữ liệu cá nhân" là câu không giúp ai quyết định gì.
     * "Xoá 4 đánh giá, 2 địa chỉ; giữ lại 15 đơn hàng" thì có.
     *
     * @return array<string, int>
     */
    public function summary(User $user): array
    {
        return [
            'donGiuLai' => Order::where('user_id', $user->id)->count(),
            'danhGiaXoa' => $user->reviews()->count(),
            'diaChiXoa' => $user->addresses()->count(),
            'yeuThichXoa' => $user->wishlists()->count(),
        ];
    }

    /**
     * Tài khoản này có được phép tự xoá không.
     *
     * @throws AccountDeletionException
     */
    private function assertDeletable(User $user): void
    {
        /*
         * KHÔNG XOÁ NỐT QUẢN TRỊ VIÊN CUỐI CÙNG.
         *
         * Cùng một luật với trang quản lý người dùng, chỉ khác là ở đây
         * người thao tác chính là người bị xoá. Không chặn thì admin duy
         * nhất tự xoá mình là cửa hàng mất hẳn đường vào khu quản trị,
         * và không màn hình nào chữa được.
         */
        if ($user->role === UserRole::Admin) {
            $conLai = User::where('role', UserRole::Admin)
                ->whereNull('locked_at')
                ->whereKeyNot($user->getKey())
                ->count();

            if ($conLai === 0) {
                throw new AccountDeletionException(
                    'Đây là tài khoản quản trị duy nhất còn hoạt động nên không thể xoá. '
                    .'Hãy chỉ định một quản trị viên khác trước.'
                );
            }
        }

        /*
         * KHÔNG XOÁ KHI CÒN ĐƠN ĐANG DỞ.
         *
         * Đơn đã giao xong hay đã huỷ thì xong chuyện. Nhưng đơn đang
         * chờ xác nhận, đang chuẩn bị hay đang giao là việc CHƯA XONG
         * giữa hai bên: khách còn có thể cần huỷ, cửa hàng còn có thể
         * cần liên hệ, và cả hai đều mất đường sau khi tài khoản biến
         * mất — đơn vẫn giao được nhờ thông tin đã chụp, nhưng khách
         * không còn chỗ nào để theo dõi hay khiếu nại.
         *
         * Chặn tạm thời, không chặn vĩnh viễn: xong đơn là xoá được.
         */
        $dangDo = Order::where('user_id', $user->id)
            ->whereNotIn('status', [
                OrderStatus::Completed->value,
                OrderStatus::Cancelled->value,
            ])
            ->count();

        if ($dangDo > 0) {
            throw new AccountDeletionException(sprintf(
                'Bạn còn %d đơn hàng chưa hoàn tất. Hãy đợi giao xong hoặc huỷ đơn trước khi '
                .'xoá tài khoản — sau khi xoá, bạn sẽ không còn chỗ nào để theo dõi đơn đó.',
                $dangDo,
            ));
        }
    }
}
