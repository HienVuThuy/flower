<?php

namespace App\Models;

use App\Enums\BoardingQuoteStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một phiên bản báo giá: các dòng chi tiết (nhãn + số tiền) và tổng, CHỤP lúc gửi —
 * báo giá sau không làm đổi báo giá trước, nên xem lại được lịch sử thương lượng.
 */
class BoardingQuote extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'lines' => 'array',
            'total' => 'decimal:2',
            'valid_until' => 'date',
            'status' => BoardingQuoteStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(BoardingBooking::class, 'boarding_booking_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function hetHan(): bool
    {
        return $this->valid_until !== null && $this->valid_until->copy()->endOfDay()->isPast();
    }
}
