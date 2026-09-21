<?php

namespace App\Models;

use App\Enums\BoardingPaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một lần thu (dương) hoặc trả lại (âm) tiền của phiếu chăm hộ. */
class BoardingPayment extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => BoardingPaymentMethod::class,
            'paid_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(BoardingBooking::class, 'boarding_booking_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
