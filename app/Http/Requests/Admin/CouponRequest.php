<?php

namespace App\Http\Requests\Admin;

use App\Enums\CouponType;
use App\Enums\PromotionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CouponRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => mb_strtoupper(trim($this->input('code')))]);
        }

        $this->merge(['is_public' => $this->boolean('is_public')]);

        $this->merge(['stack_with_member' => $this->boolean('stack_with_member')]);

        $this->merge(\App\Services\Time\Gio::doiONhap($this->all(), 'starts_at', 'ends_at'));
    }

    public function rules(): array
    {
        $id = $this->route('coupon')?->id;

        return [
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Z0-9]+$/',
                Rule::unique('coupons', 'code')->ignore($id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],

            'type' => ['required', Rule::in(CouponType::values())],
            'value' => ['required', 'numeric', 'min:0.01'],

            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],

            'per_user_limit' => ['nullable', 'integer', 'min:1', 'max:65535'],

            'is_public' => ['boolean'],

            'promotion_id' => ['nullable', 'integer', 'exists:promotions,id'],

            'stack_with_member' => ['boolean'],

            'min_member_tier_id' => ['nullable', 'integer', 'exists:member_tiers,id'],

            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*' => [Rule::in(\App\Enums\PaymentMethod::values())],

            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],

            'status' => ['required', Rule::in(array_column(PromotionStatus::cases(), 'value'))],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->input('type') === CouponType::Percent->value
                && (float) $this->input('value') > 100) {
                $v->errors()->add('value', 'Giảm theo phần trăm không được vượt quá 100.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'code' => 'mã',
            'name' => 'tên chương trình',
            'type' => 'kiểu giảm giá',
            'value' => 'giá trị giảm',
            'min_order_amount' => 'giá trị đơn tối thiểu',
            'max_discount_amount' => 'mức giảm tối đa',
            'usage_limit' => 'giới hạn lượt dùng',
            'per_user_limit' => 'giới hạn mỗi tài khoản',
            'is_public' => 'hiện ở trang Voucher',
            'promotion_id' => 'chương trình khuyến mại',
            'payment_methods' => 'hình thức thanh toán',
            'starts_at' => 'thời gian bắt đầu',
            'ends_at' => 'thời gian kết thúc',
            'status' => 'trạng thái',
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Mã chỉ được gồm chữ cái không dấu và chữ số, ví dụ NOEL2026.',
        ];
    }
}
