<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/** Sửa thông tin cá nhân. */
class ProfileRequest extends FormRequest
{
    protected $errorBag = 'profile';

    public function authorize(): bool
    {
        return Auth::check();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => trim((string) $this->input('email')),
            'email_confirmation' => trim((string) $this->input('email_confirmation')),
        ]);
    }

    public function rules(): array
    {
        $user = Auth::user();

        return [
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),

                Rule::when(
                    fn () => $this->input('email') !== $user->email,
                    ['confirmed'],
                ),
            ],

            'current_password' => [
                Rule::requiredIf(fn () => $this->input('email') !== $user->email),
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($user) {
                    if ($value !== null && $value !== '' && ! Hash::check($value, $user->password)) {
                        $fail('Mật khẩu hiện tại không đúng.');
                    }
                },
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'họ tên',
            'email' => 'email',
            'current_password' => 'mật khẩu hiện tại',
            'email_confirmation' => 'email nhập lại',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập họ tên.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không đúng định dạng.',
            'email.unique' => 'Email này đã được dùng cho tài khoản khác.',
            'email.confirmed' => 'Hai ô email không khớp nhau. Hãy kiểm tra lại.',
            'current_password.required' => 'Đổi email cần nhập mật khẩu hiện tại để xác nhận.',
        ];
    }
}
