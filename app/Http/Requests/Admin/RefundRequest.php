<?php

namespace App\Http\Requests\Admin;

use App\Enums\RefundMethod;
use App\Enums\RefundReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Kiểm HÌNH THỨC của biểu mẫu hoàn tiền.
 *
 * Luật NGHIỆP VỤ (không hoàn quá số đã trả, lý do hợp trạng thái đơn, dòng
 * hàng thuộc đúng đơn, MoMo tối thiểu 1.000₫) nằm ở RefundService, dưới
 * khoá dòng. Kiểm ở đây là kiểm trên dữ liệu có thể đã cũ một giây sau.
 */
class RefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Số nguyên: tiền Việt không có phần lẻ, và MoMo chỉ nhận số nguyên.
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
