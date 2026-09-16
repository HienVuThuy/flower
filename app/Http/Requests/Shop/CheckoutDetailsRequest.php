<?php

namespace App\Http\Requests\Shop;

use App\Enums\InvoiceBuyerType;
use App\Enums\MomoFlow;
use App\Enums\PaymentMethod;
use App\Services\Shop\Provinces;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Bước 1 gộp: người nhận + giao hàng + thanh toán. */
class CheckoutDetailsRequest extends FormRequest
{
    public function rules(): array
    {
        $batBuoc = $this->filled('address_id') ? 'nullable' : 'required';

        return [
            'address_id' => ['nullable', 'integer'],

            'recipient_name' => [$batBuoc, 'string', 'max:120'],
            'recipient_phone' => [$batBuoc, 'string', 'regex:/^0\d{9}$/'],
            'recipient_email' => ['nullable', 'email', 'max:160'],
            'shipping_address' => [$batBuoc, 'string', 'max:255'],
            'shipping_ward' => ['nullable', 'string', 'max:120'],
            'shipping_district' => ['nullable', 'string', 'max:120'],
            'shipping_province' => [
                $batBuoc,
                'string',
                'max:120',
                Rule::when(
                    ! $this->filled('to_district_id') || ! $this->filled('to_ward_code'),
                    [Rule::in(Provinces::all())],
                ),
            ],

            'to_district_id' => ['nullable', 'integer', 'min:1'],
            'to_ward_code' => ['nullable', 'string', 'max:20'],

            'save_address' => ['nullable', 'boolean'],

            'delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'delivery_note' => ['nullable', 'string', 'max:500'],

            'payment_method' => ['required', Rule::in(PaymentMethod::values())],

            'momo_flow' => ['nullable', Rule::in(MomoFlow::values())],

            'so_ky' => [
                Rule::requiredIf(fn () => $this->input('payment_method') === PaymentMethod::TraGop->value),
                'nullable', 'integer', 'min:1', 'max:24',
            ],

            'want_invoice' => ['nullable', 'boolean'],

            'invoice_buyer_type' => [
                Rule::requiredIf(fn () => $this->boolean('want_invoice')),
                Rule::enum(InvoiceBuyerType::class),
            ],

            'invoice_buyer_name' => [
                Rule::requiredIf(fn () => $this->boolean('want_invoice')),
                'nullable', 'string', 'max:200',
            ],

            'invoice_email' => [
                Rule::requiredIf(fn () => $this->boolean('want_invoice')),
                'nullable', 'email', 'max:255',
            ],

            'invoice_tax_code' => [
                Rule::requiredIf(fn () => $this->boolean('want_invoice')
                    && $this->input('invoice_buyer_type') === InvoiceBuyerType::Company->value),
                'nullable', 'string', 'regex:/^\d{10}(-\d{3})?$/',
            ],

            'invoice_address' => [
                Rule::requiredIf(fn () => $this->boolean('want_invoice')
                    && $this->input('invoice_buyer_type') === InvoiceBuyerType::Company->value),
                'nullable', 'string', 'max:300',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'recipient_name' => 'tên người nhận',
            'recipient_phone' => 'số điện thoại',
            'recipient_email' => 'email',
            'shipping_address' => 'địa chỉ',
            'shipping_ward' => 'phường/xã',
            'shipping_district' => 'quận/huyện',
            'shipping_province' => 'tỉnh/thành phố',
            'to_district_id' => 'mã quận/huyện',
            'to_ward_code' => 'mã phường/xã',
            'delivery_date' => 'ngày giao',
            'delivery_note' => 'ghi chú',
            'payment_method' => 'hình thức thanh toán',
            'momo_flow' => 'cách thanh toán MoMo',
            'so_ky' => 'số kỳ trả góp',
            'invoice_buyer_type' => 'đối tượng xuất hoá đơn',
            'invoice_buyer_name' => 'tên trên hoá đơn',
            'invoice_email' => 'email nhận hoá đơn',
            'invoice_tax_code' => 'mã số thuế',
            'invoice_address' => 'địa chỉ trên hoá đơn',
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_phone.regex' => 'Số điện thoại phải gồm 10 chữ số và bắt đầu bằng 0.',
            'delivery_date.after_or_equal' => 'Ngày giao không thể là ngày đã qua.',
            'invoice_tax_code.regex' => 'Mã số thuế gồm 10 chữ số, hoặc 10 chữ số kèm 3 số chi nhánh (ví dụ 0101234567-001).',
        ];
    }
}
