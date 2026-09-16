<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Dữ liệu lập phiếu nhập kho. */
class StockReceiptRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'note' => ['nullable', 'string', 'max:500'],

            'received_at' => ['required', 'date', 'before_or_equal:today'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.mat_hang' => ['nullable', 'string', 'max:40'],

            'items.*.quantity' => ['nullable', 'integer', 'between:-10000,10000'],

            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id' => 'nhà cung cấp',
            'received_at' => 'ngày nhập',
            'items' => 'dòng hàng',
        ];
    }

    public function messages(): array
    {
        return [
            'received_at.before_or_equal' => 'Ngày nhập không thể ở tương lai.',
            'items.required' => 'Phiếu phải có ít nhất một dòng hàng.',
        ];
    }
}
