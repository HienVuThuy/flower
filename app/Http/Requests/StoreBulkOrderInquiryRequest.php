<?php

namespace App\Http\Requests;

use App\Enums\ContactChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBulkOrderInquiryRequest extends FormRequest
{
    public const YEU_CAU_THEM = [
        'ngay' => ['event_date'],
        'dia_diem' => ['event_location'],
        'so_luong' => ['quantity_estimate'],
        'ngan_sach' => ['budget_min', 'budget_max'],
        'mau' => ['color_preference'],
        'loai_hoa' => ['flower_preference'],
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->boolean('them_form')) {
            return;
        }

        $daTick = array_map('strval', (array) $this->input('them', []));
        $bo = [];

        foreach (self::YEU_CAU_THEM as $ma => $truong) {
            if (! in_array($ma, $daTick, true)) {
                foreach ($truong as $t) {
                    $bo[$t] = null;
                }
            }
        }

        $this->merge($bo);
    }

    public function rules(): array
    {
        return [
            'contact_name' => ['required', 'string', 'max:150'],
            'contact_phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{8,20}$/'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'occasion' => ['nullable', 'string', 'max:150'],
            'quantity_estimate' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'message' => ['nullable', 'string', 'max:2000'],

            'company_name' => ['nullable', 'string', 'max:200'],

            'event_date' => ['nullable', 'date', 'after_or_equal:today'],
            'event_location' => ['nullable', 'string', 'max:255'],

            'budget_min' => ['nullable', 'integer', 'min:0', 'max:9999999999'],

            'budget_max' => array_merge(
                ['nullable', 'integer', 'min:0', 'max:9999999999'],
                $this->filled('budget_min') ? ['gte:budget_min'] : [],
            ),

            'color_preference' => ['nullable', 'string', 'max:150'],
            'flower_preference' => ['nullable', 'string', 'max:200'],

            'preferred_contact' => ['nullable', Rule::in(ContactChannel::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_name.required' => 'Vui lòng nhập họ tên.',
            'contact_phone.required' => 'Vui lòng nhập số điện thoại.',
            'contact_phone.regex' => 'Số điện thoại không hợp lệ.',
            'contact_email.email' => 'Email không đúng định dạng.',
            'product_id.exists' => 'Sản phẩm không tồn tại.',
            'quantity_estimate.integer' => 'Số lượng phải là số nguyên.',
            'quantity_estimate.min' => 'Số lượng phải lớn hơn 0.',
            'message.max' => 'Nội dung không được vượt quá 2000 ký tự.',
            'event_date.after_or_equal' => 'Ngày sự kiện phải từ hôm nay trở đi.',
            'budget_max.gte' => 'Ngân sách tối đa phải lớn hơn hoặc bằng mức tối thiểu.',
            'preferred_contact.in' => 'Cách liên hệ không hợp lệ.',
        ];
    }
}
