<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/** Khách vãng lai tra cứu đơn bằng mã đơn + số điện thoại (hoặc email). */
class OrderLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'order_number' => Str::upper(trim((string) $this->input('order_number'))),
            'contact' => trim((string) $this->input('contact')),
        ]);
    }

    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string', 'max:32'],

            'contact' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'order_number' => 'mã đơn hàng',
            'contact' => 'số điện thoại hoặc email',
        ];
    }
}
