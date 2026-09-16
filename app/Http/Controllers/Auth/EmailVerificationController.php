<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\EmailVerificationException;
use App\Services\Auth\EmailVerifier;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Xác thực email bằng mã OTP 6 chữ số. */
class EmailVerificationController extends Controller
{
    public function __construct(
        private readonly EmailVerifier $verifier,
    ) {
    }

    public function notice(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('welcome'));
        }

        return view('auth.verify-email', [
            'email' => $user->email,
            'secondsUntilResend' => $this->verifier->secondsUntilResend($user),
            'expiresAt' => $this->verifier->expiresAt($user),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate(
            [
                'code' => ['required', 'digits:6'],
            ],
            [
                'code.required' => 'Vui lòng nhập mã xác thực.',
                'code.digits' => 'Mã xác thực gồm đúng 6 chữ số.',
            ],
        );

        try {
            $this->verifier->confirm($request->user(), $data['code']);
        } catch (EmailVerificationException $e) {
            return back()
                ->withErrors(['code' => $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->intended(route('welcome'))
            ->with('success', 'Xác thực email thành công. Cảm ơn bạn!');
    }

    public function resend(Request $request): RedirectResponse
    {
        try {
            $this->verifier->send($request->user());
        } catch (EmailVerificationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã gửi mã mới. Vui lòng kiểm tra hộp thư (kể cả mục Spam).');
    }

    public function verifyLink(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        \App\Models\EmailVerificationCode::where('user_id', Auth::id())->delete();

        return redirect()
            ->intended(route('welcome'))
            ->with('success', 'Xác thực email thành công. Cảm ơn bạn!');
    }
}
