<?php

namespace App\Models;

use App\Enums\CouponType;
use App\Enums\PaymentMethod;
use App\Enums\PromotionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mã giảm giá khách tự nhập. */
class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'min_order_amount',
        'max_discount_amount',
        'usage_limit',
        'starts_at',
        'ends_at',
        'status',
        'is_public',
        'per_user_limit',
        'promotion_id',
        'payment_methods',
        'stack_with_member',
        'min_member_tier_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'status' => PromotionStatus::class,
            'value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_public' => 'boolean',
            'per_user_limit' => 'integer',
            'stack_with_member' => 'boolean',
            'min_member_tier_id' => 'integer',
            'payment_methods' => 'array',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function allowedPaymentMethods(): array
    {
        if (! is_array($this->payment_methods) || $this->payment_methods === []) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($v) => PaymentMethod::tryFrom((string) $v),
            $this->payment_methods,
        )));
    }

    public function hasPaymentRestriction(): bool
    {
        return is_array($this->payment_methods) && $this->payment_methods !== [];
    }

    public function acceptsPayment(PaymentMethod $method): bool
    {
        if (! $this->hasPaymentRestriction()) {
            return true;
        }

        return in_array($method, $this->allowedPaymentMethods(), true);
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = mb_strtoupper(trim($value));
    }

    public function scopeUsableNow(Builder $query): Builder
    {
        return $query
            ->where('status', PromotionStatus::Active)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function isRunning(): bool
    {
        if ($this->status !== PromotionStatus::Active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        return ! ($this->ends_at && $this->ends_at->isPast());
    }

    public function isExhausted(): bool
    {
        return $this->usage_limit !== null && $this->used_count >= $this->usage_limit;
    }

    public function remainingUses(): ?int
    {
        return $this->usage_limit === null
            ? null
            : max(0, $this->usage_limit - $this->used_count);
    }

    public function conditionText(): string
    {
        $parts = [];

        if ($this->min_order_amount) {
            $parts[] = 'Đơn tối thiểu ' . number_format((float) $this->min_order_amount, 0, ',', '.') . 'đ';
        }

        if ($this->type === CouponType::Percent && $this->max_discount_amount) {
            $parts[] = 'Giảm tối đa ' . number_format((float) $this->max_discount_amount, 0, ',', '.') . 'đ';
        }

        if ($this->min_member_tier_id !== null && ($hang = MemberTier::find($this->min_member_tier_id)) !== null) {
            $parts[] = 'Dành cho hạng ' . $hang->name . ' trở lên';
        }

        return $parts ? implode(' · ', $parts) : 'Không kèm điều kiện';
    }

    public function perUserText(): ?string
    {
        return match ($this->per_user_limit) {
            null => null,
            1 => 'Mỗi tài khoản dùng 1 lần',
            default => 'Mỗi tài khoản dùng tối đa '.$this->per_user_limit.' lần',
        };
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
