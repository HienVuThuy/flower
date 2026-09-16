<?php

namespace App\Services\Checkout;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart\CartService;
use App\Services\Coupon\CouponException;
use App\Services\Coupon\CouponService;
use Illuminate\Support\Collection;

/** Quyết định lần thanh toán này gồm những hàng nào. */
class CheckoutSource
{
    public const DIRECT_KEY = 'checkout.direct';

    public const COUPON_KEY = 'checkout.coupon';

    public const COUPON_AUTO_KEY = 'checkout.coupon_auto';

    public const COUPON_DECLINED_KEY = 'checkout.coupon_declined';

    public const COUPON_NOTICE_KEY = 'checkout.coupon_notice';

    public const FORM_KEY = 'checkout.form';

    public function __construct(
        private readonly CartService $cart,
        private readonly CouponService $coupons,
    ) {
    }

    public function setDirect(Product $product, int $quantity, ?ProductVariant $variant = null): void
    {
        session([
            self::DIRECT_KEY => [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'quantity' => max(1, $quantity),
            ],
        ]);
    }

    public function clearDirect(): void
    {
        session()->forget(self::DIRECT_KEY);
    }

    public function hasDirect(): bool
    {
        return session()->has(self::DIRECT_KEY);
    }

    public function setCoupon(string $code, bool $auto = false): void
    {
        session([
            self::COUPON_KEY => mb_strtoupper(trim($code)),
            self::COUPON_AUTO_KEY => $auto,
        ]);

        if (! $auto) {
            session()->forget(self::COUPON_DECLINED_KEY);
        }
    }

    public function clearCoupon(): void
    {
        session()->forget([self::COUPON_KEY, self::COUPON_AUTO_KEY]);
    }

    public function declineAutoCoupon(): void
    {
        $this->clearCoupon();

        session([self::COUPON_DECLINED_KEY => true]);
    }

    public function allowAutoCoupon(): void
    {
        session()->forget(self::COUPON_DECLINED_KEY);
    }

    public function autoCouponDeclined(): bool
    {
        return (bool) session(self::COUPON_DECLINED_KEY, false);
    }

    public function couponIsAuto(): bool
    {
        return (bool) session(self::COUPON_AUTO_KEY, false);
    }

    public function autoApplyBestCoupon(): bool
    {
        if ($this->autoCouponDeclined()) {
            return false;
        }

        if (session()->has(self::COUPON_KEY) && ! $this->couponIsAuto()) {
            return false;
        }

        $basket = $this->hasDirect() ? $this->directBasket() : $this->cartBasket();

        if ($basket->isEmpty()) {
            return false;
        }

        $best = app(\App\Services\Coupon\BestCouponFinder::class)
            ->find(\Illuminate\Support\Facades\Auth::user(), $basket->itemsTotal());

        $hang = $this->hangHienTai();

        if ($best !== null && $hang !== null && ! $best->stack_with_member) {
            $voiHang = $basket->withMemberTier($hang);

            if (bccomp($voiHang->withCoupon($best)->orderDiscountTotal(), $voiHang->orderDiscountTotal(), 2) <= 0) {
                $best = null;
            }
        }

        $current = session(self::COUPON_KEY);

        if ($best === null) {
            if ($current !== null) {
                $this->clearCoupon();

                return true;
            }

            return false;
        }

        if ($current === $best->code) {
            return false;
        }

        $this->setCoupon($best->code, auto: true);

        return true;
    }

    public function basket(): CheckoutBasket
    {
        $basket = $this->hasDirect()
            ? $this->directBasket()
            : $this->cartBasket();

        $province = session(self::FORM_KEY)['shipping_province'] ?? null;

        $form = session(self::FORM_KEY) ?? [];

        $coMa = $basket
            ->withProvince(is_string($province) ? $province : null)
            ->withGhnDestination(
                isset($form['to_district_id']) ? (int) $form['to_district_id'] : null,
                isset($form['to_ward_code']) ? (string) $form['to_ward_code'] : null,
            )
            ->withMemberTier($this->hangHienTai())
            ->withCoupon($this->resolveCoupon($basket));

        return $coMa->withPoints($this->resolvePoints($coMa));
    }

    private function hangHienTai(): ?\App\Models\MemberTier
    {
        $user = \Illuminate\Support\Facades\Auth::user();

        return $user ? app(\App\Services\Loyalty\MemberTierResolver::class)->cua($user)['hang'] : null;
    }

    public const POINTS_KEY = 'checkout.points';

    public function setPoints(int $points): void
    {
        session([self::POINTS_KEY => max(0, $points)]);
    }

    public function clearPoints(): void
    {
        session()->forget(self::POINTS_KEY);
    }

    public function requestedPoints(): int
    {
        return (int) session(self::POINTS_KEY, 0);
    }

    private function resolvePoints(CheckoutBasket $basket): int
    {
        $muon = $this->requestedPoints();
        $user = \Illuminate\Support\Facades\Auth::user();

        if ($muon <= 0 || $user === null || $basket->isEmpty()) {
            return 0;
        }

        return \App\Services\Points\PointRedemption::dungDuoc(
            $muon,
            $basket->itemsAfterCoupon(),
            app(\App\Services\Points\PointLedger::class)->soDu($user),
        );
    }

    private function resolveCoupon(CheckoutBasket $basket): ?\App\Models\Coupon
    {
        $code = session(self::COUPON_KEY);

        if (! $code || $basket->isEmpty()) {
            return null;
        }

        try {
            return $this->coupons->resolve($code, $basket->itemsTotal());
        } catch (CouponException $e) {
            $this->clearCoupon();

            session([self::COUPON_NOTICE_KEY => sprintf(
                'Mã giảm giá %s đã được gỡ khỏi đơn: %s',
                $code,
                lcfirst($e->getMessage()),
            )]);

            return null;
        }
    }

    public function cartBasket(): CheckoutBasket
    {
        $gio = $this->cart->current();

        $gio->items->loadMissing('product.taxClass');

        $lines = $gio->items
            ->filter(fn ($item) => $item->is_selected !== false)
            ->filter(fn ($item) => $item->product !== null)
            ->map(fn ($item) => new CheckoutLine(
                $item->product,
                $item->variant,
                (int) $item->quantity,
            ))
            ->values();

        return new CheckoutBasket($lines, 'cart');
    }

    private function directBasket(): CheckoutBasket
    {
        $data = session(self::DIRECT_KEY);

        $product = Product::with(['promotions', 'category', 'taxClass'])->find($data['product_id'] ?? null);

        if (! $product || $product->status !== 'active') {
            $this->clearDirect();

            return new CheckoutBasket(collect(), 'direct');
        }

        $variant = $data['variant_id']
            ? ProductVariant::where('product_id', $product->id)->find($data['variant_id'])
            : null;

        $line = new CheckoutLine($product, $variant, (int) ($data['quantity'] ?? 1));

        return new CheckoutBasket(new Collection([$line]), 'direct');
    }
}
