<?php

namespace App\Services\Auth;

use App\Mail\PasswordChangedMail;
use App\Models\User;
use App\Services\Mail\MailTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Những việc phải làm KÈM THEO khi mật khẩu thay đổi.
 * ============================================================
 * Đổi mật khẩu không chỉ là ghi một chuỗi băm mới. Người ta đổi mật
 * khẩu thường vì NGHI TÀI KHOẢN BỊ CHIẾM, nên nếu chỉ ghi mật khẩu mới
 * thì kẻ kia vẫn đang ngồi trong một phiên hợp lệ và vẫn giữ cookie
 * "ghi nhớ đăng nhập" — đổi xong họ vẫn vào được như chưa có gì xảy ra.
 *
 * BA VIỆC, phải làm đủ cả ba:
 *   1. ghi mật khẩu mới (đã băm);
 *   2. xoay remember_token → mọi cookie "ghi nhớ" cũ thành vô nghĩa;
 *   3. xoá các phiên đăng nhập KHÁC của người này.
 *
 * VÌ SAO GOM VÀO MỘT LỚP:
 * Ba chỗ cần đúng bộ ba này — đặt lại mật khẩu qua email, đổi mật khẩu
 * trong trang hồ sơ, và sau này là admin buộc đổi mật khẩu. Chép ba lần
 * thì lần thứ ba sẽ quên mất việc số 3, và quên đúng việc quan trọng
 * nhất mà không ai nhìn thấy.
 */
class AccountSecurity
{
    public function __construct(
        private readonly MailTransport $transport,
    ) {
    }

    /**
     * Đổi mật khẩu và dọn sạch mọi lối vào cũ.
     *
     * @param  string|null  $keepSessionId  phiên được giữ lại (phiên đang thao tác).
     *                                      null nghĩa là đá hết, kể cả phiên hiện tại.
     * @return int số phiên đã bị huỷ
     */
    public function changePassword(
        User $user,
        string $newPassword,
        ?string $keepSessionId = null,
        ?string $ipAddress = null,
    ): int {
        /*
         * Ghi lại địa chỉ email TRƯỚC khi đổi bất cứ thứ gì.
         *
         * Kẻ chiếm tài khoản thường đổi email trước rồi mới đổi mật khẩu.
         * Gửi cảnh báo tới địa chỉ mới là gửi thẳng cho kẻ tấn công, còn
         * chính chủ không hay biết gì. Ở đây địa chỉ chưa bị đụng tới,
         * nhưng giữ lại cho rõ ràng và để về sau ai đọc cũng thấy được ý
         * đồ này.
         */
        $notifyEmail = $user->email;

        $user->forceFill([
            'password' => Hash::make($newPassword),

            /*
             * 60 ký tự — đúng độ rộng cột remember_token của Laravel.
             * Đổi chuỗi này là mọi cookie "ghi nhớ đăng nhập" đã phát ra
             * trước đó không còn khớp, nên hết hiệu lực ngay.
             */
            'remember_token' => Str::random(60),
        ])->save();

        $killed = $this->forgetOtherSessions($user, $keepSessionId);

        /*
         * Cảnh báo cho chủ tài khoản — đặt SAU khi mọi thay đổi đã ghi
         * xong. Người tự đổi thì đã biết rồi; thư này tồn tại cho trường
         * hợp ngược lại, khi kẻ khác đổi để khoá chính chủ ra ngoài.
         *
         * Nuốt lỗi: mật khẩu đã đổi thành công rồi, không được để việc
         * gửi thư làm hỏng thao tác.
         */
        try {
            $this->transport->deliver(
                new PasswordChangedMail($user, now()->format('H:i d/m/Y'), $ipAddress),
                $notifyEmail,
            );
        } catch (\Throwable $e) {
            Log::error('Không gửi được cảnh báo đổi mật khẩu.', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return $killed;
    }

    /**
     * Xoá các phiên đăng nhập khác của người này.
     *
     * Dự án dùng SESSION_DRIVER=database nên phiên nằm ở bảng `sessions`
     * và xoá được tường minh. Cách này CHẮC CHẮN hơn dựa vào middleware
     * AuthenticateSession: middleware đó chỉ đá người dùng ra ở lần
     * request tiếp theo của họ, còn xoá bản ghi là cắt ngay lập tức.
     *
     * @return int số phiên đã bị huỷ
     */
    public function forgetOtherSessions(User $user, ?string $keepSessionId = null): int
    {
        /*
         * Trình điều khiển phiên khác (file, redis, cookie) không có
         * bảng này. Kiểm tra trước để hàm không nổ khi ai đó đổi driver;
         * lúc đó việc xoay remember_token vẫn còn tác dụng.
         */
        if (config('session.driver') !== 'database') {
            return 0;
        }

        $query = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id);

        if ($keepSessionId !== null) {
            $query->where('id', '!=', $keepSessionId);
        }

        return $query->delete();
    }
}
