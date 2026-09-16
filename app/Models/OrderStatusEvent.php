<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một bước trong dòng thời gian của đơn hàng. */
class OrderStatusEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'status',
        'changed_by',
        'note',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function actorLabel(): string
    {
        return $this->changedBy?->name ?? 'Hệ thống';
    }
}
