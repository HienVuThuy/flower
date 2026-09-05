<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Sửa thông tin cá nhân.
 *
 * KHÔNG có trường `role` ở đây, và cũng không thể có: `role` nằm ngoài
 * danh sách Fillable của User (xem chú thích trong model), nên dù request
 * có nhét thêm `role=admin` thì cũng không ghi được.
 */
class ProfileRequest extends FormRequest
{
    /**
     * Túi lỗi riêng cho biểu mẫu này.
     *
     * Trang Hồ sơ có nhiều biểu mẫu, và có ô trùng tên giữa chúng
     * (`current_password` xuất hiện ở cả biểu mẫu sửa thông tin lẫn biểu
     * mẫu đổi mật khẩu). Dùng chung túi mặc định thì lỗi của biểu mẫu này
     * hiện lên ở cả biểu mẫu kia — đo được: một câu "Mật khẩu hiện tại
     * không đúng" in ra HAI lần ở hai chỗ.
     *
     * Blade đọc túi qua thuộc tính `bag` của <x-form-error>.
     */
    protected $errorBag = 'profile';

    public function authorize(): bool
    {
        // Route đã có middleware `auth`; ở đây chỉ chắc chắn có người dùng
        // để các quy tắc bên dưới tham chiếu tới.
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

            /*
             * unique PHẢI bỏ qua chính mình, nếu không người dùng lưu lại
             * mà không đổi email cũng bị báo "email đã tồn tại".
             */
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),

                /*
                 * GÕ LẠI EMAIL MỘT LẦN NỮA khi đổi.
                 *
                 * Email là tên đăng nhập VÀ là nơi nhận liên kết đặt lại
                 * mật khẩu. Gõ nhầm một ký tự là mất cả hai đường vào
                 * cùng lúc: đăng nhập bằng địa chỉ mới thì không nhớ mình
                 * gõ gì, mà "quên mật khẩu" thì thư đi tới một hòm thư
                 * không tồn tại. Không có thao tác nào tự chữa được.
                 *
                 * `confirmed` chỉ áp khi email THẬT SỰ đổi — bắt gõ hai
                 * lần cho người chỉ muốn sửa tên là hành hạ vô cớ.
                 *
                 * ĐÂY LÀ CHẶN GÕ NHẦM, KHÔNG PHẢI XÁC MINH QUYỀN SỞ HỮU.
                 * Muốn chắc địa chỉ mới là của họ thì phải gửi thư kèm
                 * liên kết xác nhận và chỉ đổi sau khi bấm — việc đó cần
                 * cả một luồng riêng, chưa làm.
                 */
                Rule::when(
                    fn () => $this->input('email') !== $user->email,
                    ['confirmed'],
                ),
            ],

            /*
             * ĐỔI EMAIL PHẢI NHẬP MẬT KHẨU HIỆN TẠI.
             *
             * Email là tên đăng nhập, và cũng là nơi nhận liên kết đặt
             * lại mật khẩu. Ai đó mượn được máy đang mở sẵn phiên chỉ cần
             * đổi email sang địa chỉ của họ, rồi bấm "quên mật khẩu" là
             * chiếm hẳn tài khoản. Bắt nhập mật khẩu chặn đúng chỗ đó.
             *
             * Đổi mỗi TÊN thì không bắt: rủi ro thấp, mà bắt nhập mật
             * khẩu cho một việc vô hại chỉ làm người ta ngại dùng.
             */
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
