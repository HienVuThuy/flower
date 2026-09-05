<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/**
 * Đổi mật khẩu khi đang đăng nhập.
 *
 * KHÁC với màn hình "quên mật khẩu": ở đó người dùng chứng minh quyền sở
 * hữu bằng liên kết gửi qua email, còn ở đây bằng MẬT KHẨU HIỆN TẠI.
 * Thiếu bước đó thì ai mượn được máy đang mở sẵn phiên là đổi được mật
 * khẩu và khoá chính chủ ra ngoài.
 */
class ChangePasswordRequest extends FormRequest
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
    protected $errorBag = 'password';

    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            /*
             * `current_password` là quy tắc sẵn có của Laravel — nó tự
             * đối chiếu với mật khẩu của người đang đăng nhập bằng
             * Hash::check, nên không có chuyện so sánh chuỗi thô.
             */
            'current_password' => ['required', 'string', 'current_password'],

            /*
             * Quy tắc khai một nơi duy nhất ở AppServiceProvider — xem
             * chú thích ở đó. `different` chặn việc "đổi" sang đúng mật
             * khẩu cũ, thứ khiến người dùng tưởng mình đã đổi.
             */
            'password' => [
                'required',
                'confirmed',
                'different:current_password',
                Password::defaults(),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'current_password' => 'mật khẩu hiện tại',
            'password' => 'mật khẩu mới',
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'current_password.current_password' => 'Mật khẩu hiện tại không đúng.',
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'password.different' => 'Mật khẩu mới phải khác mật khẩu hiện tại.',
            'password.min' => 'Mật khẩu phải có ít nhất :min ký tự.',
            'password.mixed' => 'Mật khẩu phải có ít nhất 1 chữ hoa và 1 chữ thường.',
            'password.numbers' => 'Mật khẩu phải có ít nhất 1 chữ số.',
        ];
    }
}
