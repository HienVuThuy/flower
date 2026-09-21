<?php

namespace App\Models;

use App\Enums\PaymentTransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'order_id',
        'installment_payment_id',
        'boarding_booking_id',
        'gateway',
        'gateway_order_id',
        'transaction_id',
        'amount',
        'status',
        'result_code',
        'message',
        'request_payload',
        'response_payload',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentTransactionStatus::class,
            'result_code' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function installmentPayment(): BelongsTo
    {
        return $this->belongsTo(InstallmentPayment::class);
    }

    public function boardingBooking(): BelongsTo
    {
        return $this->belongsTo(BoardingBooking::class);
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentTransactionStatus::Paid;
    }
}
