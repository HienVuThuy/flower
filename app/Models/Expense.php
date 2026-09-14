<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một khoản chi phí vận hành. Xem migration create_expenses_table.
 *
 * `created_by`, `created_by_name` KHÔNG nằm trong $fillable: người ghi là
 * người đang đăng nhập, không phải thứ biểu mẫu gửi lên.
 */
class Expense extends Model
{
    protected $fillable = [
        'spent_on',
        'category',
        'description',
        'amount',
        'payment_method',
        'is_fixed',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'spent_on' => 'date',
            'category' => ExpenseCategory::class,
            'amount' => 'decimal:2',
            'is_fixed' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
