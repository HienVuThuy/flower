<?php

namespace App\Http\Requests\Shop;

use App\Enums\AddressLabel;
use App\Services\Shop\Provinces;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Thêm/sửa một địa chỉ trong sổ. */
class AddressRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:120'],
            'recipient_phone' => ['required', 'string', 'regex:/^0\d{9}$/'],
            'recipient_email' => ['nullable', 'email', 'max:160'],
            'address_line' => ['required', 'string', 'max:255'],
            'ward' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'province' => ['required', 'string', Rule::in(Provinces::all())],
            'label' => ['required', Rule::in(AddressLabel::values())],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'recipient_name' => 'tên người nhận',
            'recipient_phone' => 'số điện thoại',
            'recipient_email' => 'email',
            'address_line' => 'địa chỉ',
            'ward' => 'phường/xã',
            'district' => 'quận/huyện',
            'province' => 'tỉnh/thành phố',
            'label' => 'loại địa chỉ',
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_phone.regex' => 'Số điện thoại phải gồm 10 chữ số và bắt đầu bằng 0.',
        ];
    }
}
