<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;

class ReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Quyền đánh giá phụ thuộc vào sản phẩm trong URL và lịch sử mua
        // hàng, nên controller quyết định — ở đây trả false chỉ cho ra
        // một trang 403 trống, không giải thích được gì cho khách.
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],

            /*
             * Nhận xét không bắt buộc: chấm sao đã là một đánh giá hợp lệ.
             * Bắt viết chỉ khiến khách gõ "ok" cho xong.
             */
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'rating' => 'số sao',
            'comment' => 'nhận xét',
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'Vui lòng chọn số sao.',
            'rating.between' => 'Số sao phải từ 1 đến 5.',
        ];
    }
}
