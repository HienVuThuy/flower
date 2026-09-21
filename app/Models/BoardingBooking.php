<?php

namespace App\Models;

use App\Enums\BoardingHandover;
use App\Enums\BoardingMode;
use App\Enums\BoardingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Phiếu gửi cây chăm hộ. Mọi con số tiền do BoardingPricing tính ở máy chủ. */
class BoardingBooking extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'mode' => BoardingMode::class,
            'handover' => BoardingHandover::class,
            'status' => BoardingStatus::class,
            'repeat_yearly' => 'boolean',
            'waiting_next_window' => 'boolean',
            'early_return' => 'boolean',
            'drop_off_on' => 'date',
            'return_on' => 'date',
            'received_on' => 'date',
            'returned_on' => 'date',
            'paid_at' => 'datetime',
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'care_amount' => 'decimal:2',
            'handover_fee' => 'decimal:2',
            'rush_fee' => 'decimal:2',
            'adjustment' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rate(): BelongsTo
    {
        return $this->belongsTo(BoardingRate::class, 'boarding_rate_id');
    }

    public function window(): BelongsTo
    {
        return $this->belongsTo(BoardingWindow::class, 'boarding_window_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(BoardingEvent::class)->latest('id');
    }

    /** Tổng phải trả = tiền chăm + phí giao nhận + phí gấp + điều chỉnh (có thể âm), không dưới 0. */
    public function tongTien(): string
    {
        $tong = bcadd(bcadd((string) $this->care_amount, (string) $this->handover_fee, 2), bcadd((string) $this->rush_fee, (string) $this->adjustment, 2), 2);

        return bccomp($tong, '0', 2) < 0 ? '0.00' : $tong;
    }

    /** Dương = khách còn thiếu; âm = cửa hàng phải trả lại khách (ví dụ nhận cây sớm). */
    public function conLai(): string
    {
        return bcsub($this->tongTien(), (string) $this->paid_amount, 2);
    }
}
