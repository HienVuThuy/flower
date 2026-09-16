<?php

namespace App\Services\Coupon;

use App\Enums\CouponType;
use App\Enums\PaymentMethod;
use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Kiểm tra và tính mã giảm giá. */
class CouponService
{
    public function __construct(
        private readonly CouponWallet $wallet,
    ) {
    }

    public function resolve(string $code, string $itemsTotal, ?PaymentMethod $paymentMethod = null): Coupon
    {
        $code = mb_strtoupper(trim($code));

        $coupon = Coupon::where('code', $code)->first();

        if (! $coupon) {
            throw new CouponException('Mã giảm giá không tồn tại.');
        }

        $this->check($coupon, $itemsTotal, $paymentMethod);

        return $coupon;
    }

    public function check(Coupon $coupon, string $itemsTotal, ?PaymentMethod $paymentMethod = null): void
    {
        if (! $coupon->isRunning()) {
            throw new CouponException('Mã giảm giá không còn hiệu lực.');
        }

        if ($coupon->isExhausted()) {
            throw new CouponException('Mã giảm giá đã hết lượt sử dụng.');
        }

        if ($coupon->owner_user_id !== null && (int) $coupon->owner_user_id !== Auth::id()) {
            throw new CouponException('Mã giảm giá không tồn tại.');
        }

        if ($this->wallet->userLimitReached(Auth::user(), $coupon)) {
            throw new CouponException('Bạn đã dùng hết số lần cho phép của mã này.');
        }

        if ($coupon->min_member_tier_id !== null) {
            $can = \App\Models\MemberTier::find($coupon->min_member_tier_id);
            $user = Auth::user();
            $hang = $user ? app(\App\Services\Loyalty\MemberTierResolver::class)->cua($user)['hang'] : null;

            if ($can !== null && ($hang === null || bccomp((string) $hang->min_spend, (string) $can->min_spend, 2) < 0)) {
                throw new CouponException('Mã này dành cho thành viên hạng ' . $can->name . ' trở lên.');
            }
        }

        if ($paymentMethod !== null && ! $coupon->acceptsPayment($paymentMethod)) {
            $labels = array_map(
                fn (PaymentMethod $m) => $m->label(),
                $coupon->allowedPaymentMethods(),
            );

            throw new CouponException($labels === []
                ? sprintf(
                    'Mã %s bị giới hạn ở một hình thức thanh toán không còn được hỗ trợ. '
                        . 'Vui lòng dùng mã khác.',
                    $coupon->code,
                )
                : sprintf(
                    'Mã %s chỉ áp dụng khi thanh toán bằng: %s.',
                    $coupon->code,
                    implode(', ', $labels),
                ));
        }

        if ($coupon->min_order_amount && bccomp($itemsTotal, (string) $coupon->min_order_amount, 2) < 0) {
            throw new CouponException(sprintf(
                'Mã này chỉ áp dụng cho đơn từ %sđ.',
                number_format((float) $coupon->min_order_amount, 0, ',', '.'),
            ));
        }
    }

    public function reasonUnusable(Coupon $coupon, string $itemsTotal): ?string
    {
        try {
            $this->check($coupon, $itemsTotal);

            return null;
        } catch (CouponException $e) {
            return $e->getMessage();
        }
    }

    public function discountFor(Coupon $coupon, string $itemsTotal): string
    {
        $discount = match ($coupon->type) {
            CouponType::Percent => bcdiv(
                bcmul($itemsTotal, (string) $coupon->value, 4),
                '100',
                2,
            ),
            CouponType::FixedAmount => bcadd((string) $coupon->value, '0', 2),
        };

        if ($coupon->max_discount_amount
            && bccomp($discount, (string) $coupon->max_discount_amount, 2) > 0) {
            $discount = bcadd((string) $coupon->max_discount_amount, '0', 2);
        }

        if (bccomp($discount, $itemsTotal, 2) > 0) {
            $discount = $itemsTotal;
        }

        return bccomp($discount, '0', 2) < 0 ? '0.00' : $discount;
    }

    public function redeem(Coupon $coupon, Order $order): void
    {
        DB::transaction(function () use ($coupon, $order) {
            $ghiNhan = Coupon::whereKey($coupon->id)
                ->where(fn ($q) => $q
                    ->whereNull('usage_limit')
                    ->orWhereColumn('used_count', '<', 'usage_limit'))
                ->update(['used_count' => DB::raw('used_count + 1')]);

            if ($ghiNhan === 0) {
                throw new CouponException(
                    'Mã '.$coupon->code.' vừa hết lượt sử dụng. '
                    .'Vui lòng bỏ mã và đặt lại đơn.'
                );
            }

            $this->wallet->markUsed($order->user, $coupon);
        });
    }

    public function release(Order $order): void
    {
        if (! $order->coupon_id) {
            return;
        }

        Coupon::whereKey($order->coupon_id)
            ->where('used_count', '>', 0)
            ->decrement('used_count');

        $coupon = Coupon::find($order->coupon_id);

        if ($coupon) {
            $this->wallet->releaseUse($order->user, $coupon);
        }
    }
}
