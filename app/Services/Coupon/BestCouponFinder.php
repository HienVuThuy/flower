<?php

namespace App\Services\Coupon;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Tự chọn mã giảm giá TỐT NHẤT trong VÍ của khách. */
class BestCouponFinder
{
    public function __construct(
        private readonly CouponService $coupons,
    ) {
    }

    public function find(?User $user, string $itemsTotal): ?Coupon
    {
        $best = null;
        $bestDiscount = '0.00';

        foreach ($this->candidates($user) as $coupon) {
            try {
                $this->coupons->resolve($coupon->code, $itemsTotal);
            } catch (CouponException) {
                continue;
            }

            $discount = $this->coupons->discountFor($coupon, $itemsTotal);

            if (bccomp($discount, $bestDiscount, 2) > 0) {
                $best = $coupon;
                $bestDiscount = $discount;
            }
        }

        return $best;
    }

    private function candidates(?User $user): \Illuminate\Support\Collection
    {
        if ($user === null) {
            return collect();
        }

        $walletIds = DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->pluck('coupon_id');

        if ($walletIds->isEmpty()) {
            return collect();
        }

        return Coupon::query()
            ->whereIn('id', $walletIds)
            ->usableNow()
            ->get();
    }
}
