<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\ReviewRequest;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

/** Khách viết và gỡ đánh giá sản phẩm. */
class ReviewController extends Controller
{
    public function store(ReviewRequest $request, Product $product): RedirectResponse
    {
        $userId = Auth::id();

        $order = Review::pendingOrderFor($userId, $product->id);

        if (! $order) {
            return back()->with('error',
                'Bạn chỉ đánh giá được sản phẩm đã mua và đã nhận hàng. '
                . 'Mỗi đơn hàng viết được một đánh giá.');
        }

        $review = Review::create([
            'product_id' => $product->id,
            'user_id' => $userId,
            'order_id' => $order->id,
            'rating' => $request->validated('rating'),
            'comment' => $request->validated('comment'),
        ]);

        $diem = app(\App\Services\Points\PointEarning::class)->danhGia($review);

        return back()->with('success', 'Cảm ơn bạn đã đánh giá sản phẩm.'
            . ($diem > 0 ? ' Bạn được cộng ' . $diem . ' điểm thưởng.' : ''));
    }

    public function destroy(Review $review): RedirectResponse
    {
        abort_unless($review->user_id === Auth::id(), 403);

        $review->delete();

        return back()->with('success', 'Đã gỡ đánh giá của bạn.');
    }

    public function mine(): View
    {
        $reviews = Review::query()
            ->where('user_id', Auth::id())
            ->with(['product' => fn ($q) => $q->withTrashed()])
            ->latest()
            ->paginate(10);

        return view('shop.reviews.mine', compact('reviews'));
    }
}