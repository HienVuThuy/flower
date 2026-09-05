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
        // Mã luôn lưu chữ hoa để so sánh không phụ thuộc cách gõ.
        if ($this->filled('code')) {
            $this->merge(['code' => mb_strtoupper(trim($this->input('code')))]);
        }

        // Ô đánh dấu không gửi gì lên khi bỏ tích. Không đặt lại ở đây
        // thì lần lưu sau `is_public` vắng mặt, validated() không có khoá
        // đó, và cột giữ nguyên giá trị cũ — bỏ tích mà không tắt được.
        $this->merge(['is_public' => $this->boolean('is_public')]);
    }

    public function rules(): array
    {
        $id = $this->route('coupon')?->id;

        return [
            'code' => [
                'required',
                'string',
                'max:32',
                // Chỉ chữ và số: mã có dấu cách hoặc dấu tiếng Việt rất
                // dễ gõ sai khi khách chép tay từ banner.
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

            /*
             * per_user_limit LÀ GIỚI HẠN KHÁC HẲN usage_limit.
             * usage_limit  : tổng lượt trên toàn hệ thống.
             * per_user_limit: mỗi tài khoản được dùng mấy lần.
             * Không có cái thứ hai thì một người dùng hết sạch 100 lượt
             * của chương trình vẫn là hợp lệ.
             */
            'per_user_limit' => ['nullable', 'integer', 'min:1', 'max:65535'],

            // Ô đánh dấu không được gửi lên khi bỏ tích — đó là cách HTML
            // hoạt động. Vì thế 'boolean' + prepareForValidation, không
            // phải 'required'.
            'is_public' => ['boolean'],

            /*
             * MÃ CỦA MỘT SỰ KIỆN.
             * Gắn vào chương trình nào thì mã chỉ hiện ở trang sự kiện đó.
             * Bỏ trống = mã chung, hiện ở trang Voucher.
             */
            'promotion_id' => ['nullable', 'integer', 'exists:promotions,id'],

            /*
             * Giới hạn hình thức thanh toán. Bỏ trống = mọi hình thức.
             * ĐƯỢC KIỂM TRA THẬT lúc đặt hàng — xem CouponService::resolve().
             */
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
            // Giảm theo phần trăm thì giá trị phải nằm trong 0-100.
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
