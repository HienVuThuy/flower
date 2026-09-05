<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một bước trong dòng thời gian của đơn hàng.
 * ============================================================
 * Bản ghi này KHÁCH ĐỌC ĐƯỢC. Mọi thứ viết vào `note` đều phải viết như
 * đang nói với khách — ghi chú nội bộ có chỗ riêng (orders.admin_note).
 */
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

    /**
     * Ai đã làm bước này — dành cho MÀN HÌNH QUẢN TRỊ.
     *
     * KHÔNG dùng ở trang của khách. Khách không cần và không nên biết
     * tên nhân viên nào bấm nút; với họ đó là "cửa hàng".
     */
    public function actorLabel(): string
    {
        return $this->changedBy?->name ?? 'Hệ thống';
    }
}
