<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Đặt mật khẩu mới bằng liên kết trong thư.
 *
 * `token` và `email` đến từ ô ẩn trong biểu mẫu, tức là SỬA ĐƯỢC. Ở đây
 * chỉ kiểm tra định dạng; việc token có đúng, còn hạn và thuộc về email
 * đó hay không do Password broker của Laravel đối chiếu — nó so mã băm
 * của token với bản ghi trong bảng password_reset_tokens.
 */
class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => trim((string) $this->input('email')),
        ]);
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],

            /*
             * Quy tắc mật khẩu khai một nơi duy nhất ở AppServiceProvider.
             * Nếu chép tay lại ở đây thì màn hình đặt lại mật khẩu sẽ dễ
             * dãi hơn màn hình đăng ký ngay lần đầu ai đó siết quy tắc —
             * và đó là lệch theo đúng hướng nguy hiểm.
             */
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'email',
            'password' => 'mật khẩu',
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'Liên kết đặt lại mật khẩu không hợp lệ.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không đúng định dạng.',
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'password.min' => 'Mật khẩu phải có ít nhất :min ký tự.',
            'password.mixed' => 'Mật khẩu phải có ít nhất 1 chữ hoa và 1 chữ thường.',
            'password.numbers' => 'Mật khẩu phải có ít nhất 1 chữ số.',
        ];
    }
}
