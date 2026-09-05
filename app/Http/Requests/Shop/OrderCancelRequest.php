<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Khách tự huỷ đơn.
 *
 * Quyền huỷ (đúng người, đúng trạng thái) do controller kiểm tra, không
 * đặt ở authorize() — ở đó chưa có đơn hàng đã nạp qua route binding và
 * trả về false sẽ cho khách xem trang 403 trống thay vì một câu giải
 * thích tử tế.
 */
class OrderCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /*
             * Lý do KHÔNG bắt buộc.
             *
             * Ép khách giải thích vì sao muốn huỷ chỉ làm họ gõ bừa cho
             * xong. Cửa hàng cần con số thật về lý do huỷ thì phải hỏi
             * bằng cách khác, không phải bằng một ô bắt buộc.
             */
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'reason' => 'lý do huỷ',
        ];
    }
}
