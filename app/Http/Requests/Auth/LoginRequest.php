<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
            ],

            'password' => [
                'required',
                'string',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Vui lòng nhập email.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ];
    }

    /**
     * Thử đăng nhập, có giới hạn số lần thử để chống dò
     * mật khẩu (brute force).
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $remember = $this->boolean('remember');

        if (! Auth::attempt($this->only('email', 'password'), $remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Email hoặc mật khẩu không đúng.',
            ]);
        }

        /*
         * TÀI KHOẢN BỊ KHOÁ: ĐĂNG XUẤT NGAY, ĐỪNG CHO ĐI TIẾP.
         *
         * Kiểm SAU Auth::attempt() chứ không trước, và đó là chủ ý: kiểm
         * trước thì phải tra email trong bảng users khi CHƯA biết người
         * gõ có đúng mật khẩu hay không. Khi ấy thông báo "tài khoản đã
         * bị khoá" trở thành một cách để người lạ dò xem email nào có
         * tồn tại và email nào đang bị khoá.
         *
         * Kiểm sau thì chỉ người biết đúng mật khẩu mới đọc được lý do —
         * tức là đúng chủ tài khoản, đúng người cần biết.
         *
         * Auth::logout() ngay lập tức: attempt() đã đăng nhập họ vào
         * phiên rồi, không gỡ ra thì trang sau vẫn coi như đã đăng nhập.
         */
        $user = Auth::user();

        if ($user && $user->isLocked()) {
            Auth::logout();

            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => $user->lock_reason
                    ? 'Tài khoản của bạn đang bị khoá. Lý do: '.$user->lock_reason
                    : 'Tài khoản của bạn đang bị khoá. Vui lòng liên hệ cửa hàng.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            $seconds = RateLimiter::availableIn($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => "Bạn đã đăng nhập sai quá nhiều lần. Vui lòng thử lại sau {$seconds} giây.",
            ]);
        }
    }

    protected function throttleKey(): string
    {
        return Str::lower($this->string('email')).'|'.$this->ip();
    }
}
