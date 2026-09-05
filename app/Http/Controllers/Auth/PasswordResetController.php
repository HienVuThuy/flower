<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\AccountSecurity;
use App\Services\Auth\PasswordResetMailer;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Quên mật khẩu / đặt lại mật khẩu.
 * ============================================================
 * TÁCH RIÊNG khỏi AuthController: đăng nhập/đăng ký là "vào nhà bằng
 * chìa khoá đang có", còn luồng này là "làm lại chìa khoá" — nhiều bước,
 * nhiều ràng buộc bảo mật riêng, và mỗi thứ một vòng đời.
 *
 * DÙNG PASSWORD BROKER CỦA LARAVEL, KHÔNG TỰ VIẾT.
 * Broker đã lo sẵn bốn thứ mà tự viết rất dễ làm sai:
 *   - token sinh bằng nguồn ngẫu nhiên an toàn;
 *   - token lưu trong cơ sở dữ liệu dưới dạng MÃ BĂM (rò cơ sở dữ liệu
 *     cũng không dùng lại được token);
 *   - hạn dùng (config auth.passwords.users.expire = 60 phút);
 *   - dùng MỘT LẦN — token bị xoá ngay khi đổi mật khẩu thành công.
 *
 * BỐN LỚP CHỐNG LẠM DỤNG:
 *   1. Không lộ email nào có thật (xem sendLink).
 *   2. Giới hạn theo email — broker tự chặn, config `throttle` = 60 giây.
 *   3. Giới hạn theo IP — middleware throttle khai ở routes/web.php.
 *   4. Đổi mật khẩu xong thì huỷ mọi phiên đăng nhập khác (xem reset).
 */
class PasswordResetController extends Controller
{
    public function __construct(
        private readonly PasswordResetMailer $mailer,
    ) {
    }

    /* ============ BƯỚC 1: NHẬP EMAIL ============ */

    public function showLinkForm(): View
    {
        return view('auth.forgot-password', [
            // Môi trường chưa gửi thư thật được thì nói thẳng cho người
            // đang phát triển, thay vì để họ ngồi chờ một thư không đến.
            'mailWorks' => $this->mailer->deliversForReal(),
        ]);
    }

    public function sendLink(ForgotPasswordRequest $request): RedirectResponse
    {
        $email = $request->validated('email');

        /*
         * KHÔNG dùng Password::sendResetLink() trực tiếp.
         *
         * Hàm đó trả về mã trạng thái khác nhau cho "đã gửi" và "không
         * tìm thấy người dùng". Hiện mã đó ra màn hình là biến biểu mẫu
         * này thành công cụ dò tài khoản: nhập một địa chỉ là biết ngay
         * email đó có đăng ký hay chưa.
         *
         * Ở đây vẫn gọi broker để nó làm phần việc của nó, nhưng câu trả
         * lời gửi về màn hình LUÔN GIỐNG NHAU.
         */
        $status = Password::sendResetLink(['email' => $email]);

        /*
         * Ghi log để quản trị viên còn lần ra được sự cố thật — nhưng
         * chỉ ghi mã trạng thái, KHÔNG ghi địa chỉ email.
         */
        if ($status !== Password::RESET_LINK_SENT && $status !== Password::INVALID_USER) {
            Log::warning('Yêu cầu đặt lại mật khẩu không hoàn tất.', ['status' => $status]);
        }

        return back()->with('success', $this->neutralMessage());
    }

    /* ============ BƯỚC 2: ĐẶT MẬT KHẨU MỚI ============ */

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            // Email đi kèm trên URL để điền sẵn ô nhập. Không tin nó —
            // broker vẫn đối chiếu token với đúng email khi gửi biểu mẫu.
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) use ($request) {
                /*
                 * ĐI QUA AccountSecurity, không tự ghi mật khẩu ở đây.
                 *
                 * Bản trước chỉ đổi mật khẩu và remember_token — tức là
                 * cookie "ghi nhớ" bị vô hiệu, nhưng những PHIÊN ĐANG MỞ
                 * của kẻ chiếm tài khoản thì vẫn sống. Người đặt lại mật
                 * khẩu thường vì nghi bị chiếm, nên bỏ sót đúng chỗ nguy
                 * hiểm nhất.
                 *
                 * keepSessionId = null: đá HẾT mọi phiên, kể cả phiên
                 * hiện tại. Người dùng đang ở màn hình đặt lại mật khẩu
                 * và chưa đăng nhập, nên không mất gì; còn kẻ tấn công
                 * thì mất sạch.
                 */
                app(AccountSecurity::class)->changePassword(
                    $user,
                    $password,
                    null,
                    $request->ip(),
                );

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            /*
             * Token sai, hết hạn, hoặc đã dùng rồi. Gộp chung một thông
             * báo: phân biệt "sai" với "hết hạn" chỉ giúp người dò token
             * biết mình đang đi đúng hướng.
             */
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        return redirect()
            ->route('login')
            ->with('success', 'Đã đặt lại mật khẩu. Bạn có thể đăng nhập bằng mật khẩu mới.');
    }

    /**
     * Câu trả lời DUY NHẤT cho bước nhập email.
     *
     * Giống nhau dù email có tồn tại hay không, dù thư gửi được hay
     * không. Đây là điều duy nhất ngăn biểu mẫu này thành công cụ dò
     * danh sách khách hàng.
     */
    private function neutralMessage(): string
    {
        return 'Nếu email này có tài khoản, chúng tôi đã gửi liên kết đặt lại mật khẩu. '
            . 'Vui lòng kiểm tra hộp thư (kể cả mục Spam).';
    }
}
