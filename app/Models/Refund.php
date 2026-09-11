<?php

namespace App\Models;

use App\Enums\RefundMethod;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một lần trả tiền lại cho khách.
 *
 * `$fillable` CỐ Ý HẸP. `status`, `completed_at`, `reference`,
 * `gateway_*` và người lập chỉ được ghi bởi RefundService, đúng lúc việc
 * tương ứng xảy ra — không có biểu mẫu nào đặt được "đã hoàn" cho một
 * khoản tiền chưa đi.
 */
class Refund extends Model
{
    protected $fillable = [
        'order_id',
        'code',
        'amount',
        'reason',
        'note',
        'method',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reason' => RefundReason::class,
            'method' => RefundMethod::class,
            'status' => RefundStatus::class,
            'gateway_response' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RefundItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function actorLabel(): string
    {
        return $this->createdBy?->name ?? $this->created_by_name ?? 'Không rõ';
    }
}
