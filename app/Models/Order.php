<?php

namespace App\Models;

use App\Enums\GhnFeePayer;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_number',
        'idempotency_key',
        'user_id',
        'recipient_name',
        'recipient_phone',
        'recipient_email',
        'shipping_address',
        'shipping_ward',
        'shipping_district',
        'shipping_province',
        'delivery_date',
        'delivery_note',
        'payment_method',
        'subtotal',
        'discount_total',
        'shipping_fee',
        'ghn_order_code',
        'ghn_total_fee',
        'to_district_id',
        'to_ward_code',
        'shipping_status',
        'ghn_expected_from',
        'ghn_expected_to',
        'coupon_id',
        'coupon_code',
        'coupon_discount',
        'points_used',
        'points_discount',
        'member_tier_code',
        'member_discount',
        'grand_total',
        'tax_rate',
        'tax_amount',
        'shipping_tax_amount',
    ];

    protected $attributes = [
        'status' => 'pending',
        'payment_status' => 'unpaid',
    ];

    protected function casts(): array
    {
        return [
            'risk_score' => 'integer',
            'risk_flags' => 'array',
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'delivery_date' => 'date',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'coupon_discount' => 'decimal:2',
            'points_used' => 'integer',
            'points_discount' => 'decimal:2',
            'member_discount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'grand_total' => 'decimal:2',

            'tax_rate' => 'decimal:5',
            'tax_amount' => 'decimal:2',
            'shipping_tax_amount' => 'decimal:2',
            'ghn_expected_from' => 'datetime',
            'ghn_expected_to' => 'datetime',

            'ghn_total_fee' => 'integer',

            'ghn_fee_payer' => GhnFeePayer::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class)->latest('id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->latest('id');
    }

    public function refundedAmount(): string
    {
        return $this->congTienHoan(fn (Refund $r) => $r->status === RefundStatus::Completed);
    }

    public function reservedRefundAmount(): string
    {
        return $this->congTienHoan(fn (Refund $r) => $r->status->giuChoTien());
    }

    public function installmentPlan(): HasOne
    {
        return $this->hasOne(InstallmentPlan::class);
    }

    public function daThu(): string
    {
        $keHoach = $this->installmentPlan;

        if ($keHoach !== null) {
            return $keHoach->loadMissing('payments')->daTra();
        }

        return in_array($this->payment_status, [PaymentStatus::Paid, PaymentStatus::Refunded], true)
            ? (string) $this->grand_total
            : '0.00';
    }

    public function choDoiTraGop(): bool
    {
        return $this->payment_method === PaymentMethod::TraGop
            && $this->payment_status !== PaymentStatus::Paid;
    }

    public function refundableAmount(): string
    {
        $con = bcsub($this->daThu(), $this->reservedRefundAmount(), 2);

        return bccomp($con, '0', 2) > 0 ? $con : '0.00';
    }

    private function congTienHoan(callable $loc): string
    {
        $tong = '0.00';

        foreach ($this->refunds->filter($loc) as $r) {
            $tong = bcadd($tong, (string) $r->amount, 2);
        }

        return $tong;
    }

    public function itemsTax(): ?string
    {
        if ($this->tax_amount === null) {
            return null;
        }

        return bcsub((string) $this->tax_amount, (string) ($this->shipping_tax_amount ?? '0.00'), 2);
    }

    public function netTotal(): ?string
    {
        if ($this->tax_amount === null) {
            return null;
        }

        return bcsub((string) $this->grand_total, (string) $this->tax_amount, 2);
    }

    public function taxByRate(): array
    {
        if ($this->tax_amount === null) {
            return [];
        }

        $nhom = [];

        $gop = function (?string $rate, string $net, string $tax) use (&$nhom): void {
            $khoa = $rate === null ? '' : (string) (float) $rate;

            $nhom[$khoa] ??= ['rate' => $rate, 'net' => '0.00', 'tax' => '0.00'];
            $nhom[$khoa]['net'] = bcadd($nhom[$khoa]['net'], $net, 2);
            $nhom[$khoa]['tax'] = bcadd($nhom[$khoa]['tax'], $tax, 2);
        };

        foreach ($this->items as $item) {
            $gop(
                $item->tax_rate === null ? null : (string) $item->tax_rate,
                $item->netTotal(),
                (string) ($item->tax_amount ?? '0.00'),
            );
        }

        $phi = (string) $this->shipping_fee;

        if (bccomp($phi, '0', 2) > 0) {
            $thuePhi = (string) ($this->shipping_tax_amount ?? '0.00');
            $gop(
                $this->tax_rate === null ? null : (string) $this->tax_rate,
                bcsub($phi, $thuePhi, 2),
                $thuePhi,
            );
        }

        $rows = array_values($nhom);

        usort($rows, function (array $a, array $b): int {
            if ($a['rate'] === null) {
                return $b['rate'] === null ? 0 : 1;
            }

            if ($b['rate'] === null) {
                return -1;
            }

            return bccomp($b['rate'], $a['rate'], 6);
        });

        return $rows;
    }

    public function scopeAwaitingWaybill(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::Preparing->value])
            ->whereNull('ghn_order_code');
    }

    public function scopeRefundPending(Builder $query): Builder
    {
        return $query->whereHas('refunds', fn ($q) => $q->where('status', RefundStatus::Pending->value));
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            OrderStatus::Completed->value,
            OrderStatus::Cancelled->value,
        ]);
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function totalQuantity(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function isCancellable(): bool
    {
        return $this->status->canTransitionTo(OrderStatus::Cancelled);
    }

    public function isCancellableByCustomer(): bool
    {
        return in_array($this->status, [
            OrderStatus::Pending,
            OrderStatus::Confirmed,
        ], strict: true);
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(OrderStatusEvent::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function exchanges(): HasMany
    {
        return $this->hasMany(Exchange::class)->latest('id');
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    public function needsRiskReview(): bool
    {
        return $this->risk_score >= (int) config('risk.review_from', 40);
    }

    public function riskFlags(): array
    {
        return is_array($this->risk_flags) ? $this->risk_flags : [];
    }
}
