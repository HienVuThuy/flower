<?php

namespace App\Http\Requests\Admin;

use App\Enums\RefundMethod;
use App\Enums\RefundReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Kiểm HÌNH THỨC của biểu mẫu hoàn tiền. */
class RefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'reason' => ['required', Rule::enum(RefundReason::class)],
            'method' => ['required', Rule::enum(RefundMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['nullable', 'array', 'max:200'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'items.*.restock' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'amount' => 'số tiền hoàn',
            'reason' => 'lý do',
            'method' => 'cách hoàn',
            'reference' => 'mã giao dịch',
            'note' => 'ghi chú',
            'items.*.quantity' => 'số lượng trả về',
        ];
    }
}
