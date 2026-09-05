<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Yêu cầu gửi liên kết đặt lại mật khẩu.
 *
 * CỐ Ý KHÔNG dùng `exists:users,email`.
 *
 * Quy tắc đó biến biểu mẫu này thành công cụ dò tài khoản: nhập một địa
 * chỉ, thấy lỗi "email không tồn tại" là biết ngay email nào CHƯA đăng
 * ký, và ngược lại. Kẻ tấn công quét vài nghìn địa chỉ là có danh sách
 * khách hàng của cửa hàng.
 *
 * Ở đây chỉ kiểm tra ĐỊNH DẠNG. Việc email có tồn tại hay không do
 * controller xử lý, và câu trả lời gửi về luôn giống nhau dù có hay
 * không — xem PasswordResetController.
 */
class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Khách hay chép email kèm dấu cách ở đầu/cuối.
        $this->merge([
            'email' => trim((string) $this->input('email')),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['email' => 'email'];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không đúng định dạng.',
        ];
    }
}
