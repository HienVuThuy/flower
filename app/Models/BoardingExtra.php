<?php

namespace App\Models;

use App\Enums\BoardingExtraStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một việc làm thêm cho cây đang gửi (thay chậu, tạo dáng, xử lý sâu…) — giá báo riêng từng việc. */
class BoardingExtra extends Model
{
    public const KHACH = 'khach';

    public const CUA_HANG = 'cua_hang';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'status' => BoardingExtraStatus::class,
            'price' => 'decimal:2',
            'quoted_at' => 'datetime',
            'answered_at' => 'datetime',
            'done_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(BoardingBooking::class, 'boarding_booking_id');
    }
}
