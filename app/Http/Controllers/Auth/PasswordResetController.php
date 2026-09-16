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

/** Quên mật khẩu / đặt lại mật khẩu. */
class PasswordResetController extends Controller
{
    public function __construct(
        private readonly PasswordResetMailer $mailer,
    ) {
    }

    public function showLinkForm(): View
    {
        return view('auth.forgot-password', [
            'mailWorks' => $this->mailer->deliversForReal(),
        ]);
    }

    public function sendLink(ForgotPasswordRequest $request): RedirectResponse
    {
        $email = $request->validated('email');

        $status = Password::sendResetLink(['email' => $email]);

        if ($status !== Password::RESET_LINK_SENT && $status !== Password::INVALID_USER) {
            Log::warning('Yêu cầu đặt lại mật khẩu không hoàn tất.', ['status' => $status]);
        }

        return back()->with('success', $this->neutralMessage());
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) use ($request) {
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
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        return redirect()
            ->route('login')
            ->with('success', 'Đã đặt lại mật khẩu. Bạn có thể đăng nhập bằng mật khẩu mới.');
    }

    private function neutralMessage(): string
    {
        return 'Nếu email này có tài khoản, chúng tôi đã gửi liên kết đặt lại mật khẩu. '
            . 'Vui lòng kiểm tra hộp thư (kể cả mục Spam).';
    }
}
