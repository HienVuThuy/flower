<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\Coupon\CouponWallet;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Trang sự kiện — landing page riêng cho một chương trình khuyến mại. */
class EventController extends Controller
{
    public function __construct(
        private readonly CouponWallet $wallet,
    ) {
    }

    public function show(Promotion $promotion): View
    {
        abort_if($promotion->status === \App\Enums\PromotionStatus::Draft, 404);

        $products = Product::query()
            ->with(['category', 'promotions',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->whereHas('promotions', fn ($q) => $q->where('promotions.id', $promotion->id))
            ->whereIn('status', ['active', 'out_of_stock'])
            ->orderByEffectivePrice('asc')
            ->get();

        $coupons = Coupon::query()
            ->where('promotion_id', $promotion->id)
            ->where('is_public', true)
            ->orderByDesc('value')
            ->get();

        $user = Auth::user();

        return view('shop.events.show', [
            'promotion' => $promotion,
            'products' => $products,
            'coupons' => $coupons,

            'claimedIds' => $user
                ? $this->wallet->forUser($user)->pluck('coupon.id')->all()
                : [],

            'isRunning' => $promotion->isRunning(),
        ]);
    }
}
