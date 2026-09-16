<?php

namespace App\Services\Checkout;

use App\Models\Coupon;
use App\Models\MemberTier;
use App\Services\Coupon\CouponService;
use App\Services\Points\PointRedemption;
use App\Services\Shipping\ShippingQuote;
use App\Services\Shipping\ShippingRates;
use App\Services\Tax\BasketTax;
use Illuminate\Support\Collection;

/** Toàn bộ số tiền của một lần thanh toán. */
final readonly class CheckoutBasket
{
    public function __construct(
        public Collection $lines,
        public string $source = 'cart',
        public ?Coupon $coupon = null,
        public ?string $province = null,

        public ?int $toDistrictId = null,
        public ?string $toWardCode = null,

        public int $points = 0,

        public ?MemberTier $memberTier = null,
    ) {
    }

    public function withCoupon(?Coupon $coupon): self
    {
        return new self(
            $this->lines, $this->source, $coupon,
            $this->province, $this->toDistrictId, $this->toWardCode, $this->points, $this->memberTier,
        );
    }

    public function withProvince(?string $province): self
    {
        return new self(
            $this->lines, $this->source, $this->coupon,
            $province, $this->toDistrictId, $this->toWardCode, $this->points, $this->memberTier,
        );
    }

    public function withGhnDestination(?int $districtId, ?string $wardCode): self
    {
        return new self(
            $this->lines, $this->source, $this->coupon,
            $this->province, $districtId, $wardCode, $this->points, $this->memberTier,
        );
    }

    public function withPoints(int $points): self
    {
        return new self(
            $this->lines, $this->source, $this->coupon,
            $this->province, $this->toDistrictId, $this->toWardCode, max(0, $points), $this->memberTier,
        );
    }

    public function withMemberTier(?MemberTier $tier): self
    {
        return new self(
            $this->lines, $this->source, $this->coupon,
            $this->province, $this->toDistrictId, $this->toWardCode, $this->points, $tier,
        );
    }

    public function hasDestination(): bool
    {
        return $this->province !== null && trim($this->province) !== '';
    }

    public function isEmpty(): bool
    {
        return $this->lines->isEmpty();
    }

    public function totalQuantity(): int
    {
        return (int) $this->lines->sum(fn (CheckoutLine $l) => $l->quantity);
    }

    public function itemsTotal(): string
    {
        return $this->lines->reduce(
            fn (string $carry, CheckoutLine $l) => bcadd($carry, $l->lineTotal(), 2),
            '0',
        );
    }

    public function baseTotal(): string
    {
        return $this->lines->reduce(
            fn (string $carry, CheckoutLine $l) => bcadd(
                $carry,
                bcmul($l->unitBasePrice(), (string) $l->quantity, 2),
                2,
            ),
            '0',
        );
    }

    public function discountTotal(): string
    {
        return bcsub($this->baseTotal(), $this->itemsTotal(), 2);
    }

    private function memberDiscountPercent(): string
    {
        return $this->memberTier ? bcadd((string) $this->memberTier->discount_percent, '0', 2) : '0.00';
    }

    public function memberDiscountBlockedByCoupon(): bool
    {
        return $this->coupon !== null
            && ! $this->coupon->stack_with_member
            && bccomp($this->memberDiscountPercent(), '0', 2) > 0;
    }

    public function memberDiscount(): string
    {
        if (bccomp($this->memberDiscountPercent(), '0', 2) <= 0 || $this->memberDiscountBlockedByCoupon()) {
            return '0.00';
        }

        return bcadd(bcdiv(bcmul($this->itemsTotal(), $this->memberDiscountPercent(), 4), '100', 0), '0', 2);
    }

    public function itemsAfterMember(): string
    {
        return bcsub($this->itemsTotal(), $this->memberDiscount(), 2);
    }

    public function couponDiscount(): string
    {
        if (! $this->coupon) {
            return '0.00';
        }

        return app(CouponService::class)->discountFor($this->coupon, $this->itemsAfterMember());
    }

    public function itemsAfterCoupon(): string
    {
        return bcsub($this->itemsAfterMember(), $this->couponDiscount(), 2);
    }

    public function pointsUsed(): int
    {
        return PointRedemption::dungDuoc($this->points, $this->itemsAfterCoupon(), $this->points);
    }

    public function pointsDiscount(): string
    {
        return PointRedemption::quyRaTien($this->pointsUsed());
    }

    public function orderDiscountTotal(): string
    {
        return bcadd(bcadd($this->memberDiscount(), $this->couponDiscount(), 2), $this->pointsDiscount(), 2);
    }

    public function payableItemsTotal(): string
    {
        return bcsub($this->itemsTotal(), $this->orderDiscountTotal(), 2);
    }

    public function isFreeShipping(): bool
    {
        return bccomp($this->itemsTotal(), $this->freeShippingThreshold(), 2) >= 0;
    }

    public function shippingFee(): string
    {
        if ($this->isFreeShipping()) {
            return '0.00';
        }

        return $this->baseShippingFee();
    }

    public function baseShippingFee(): string
    {
        if (! $this->hasDestination()) {
            return '0.00';
        }

        return app(ShippingQuote::class)->feeFor($this, $this->toDistrictId, $this->toWardCode);
    }

    public function shippingDiscount(): string
    {
        if (! $this->hasDestination()) {
            return '0.00';
        }

        return $this->isFreeShipping() ? $this->baseShippingFee() : '0.00';
    }

    public function shippingZoneLabel(): string
    {
        if ($this->hasGhnDestination()) {
            return 'cước Giao Hàng Nhanh';
        }

        return app(ShippingRates::class)->zoneLabel($this->province);
    }

    public function hasGhnDestination(): bool
    {
        return $this->toDistrictId !== null && $this->toWardCode !== null;
    }

    public function amountToFreeShipping(): string
    {
        return bcsub($this->freeShippingThreshold(), $this->itemsTotal(), 2);
    }

    public function grandTotal(): string
    {
        return bcadd($this->payableItemsTotal(), $this->shippingFee(), 2);
    }

    public function tax(): BasketTax
    {
        return BasketTax::for($this);
    }

    public function freeShippingFrom(): string
    {
        return $this->freeShippingThreshold();
    }

    public function freeShippingByTier(): bool
    {
        $hang = $this->memberTier?->free_shipping_from;

        return $hang !== null && bccomp((string) $hang, app(ShippingRates::class)->freeFrom(), 2) < 0;
    }

    private function freeShippingThreshold(): string
    {
        $chung = app(ShippingRates::class)->freeFrom();

        return $this->freeShippingByTier()
            ? bcadd((string) $this->memberTier->free_shipping_from, '0', 2)
            : $chung;
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
