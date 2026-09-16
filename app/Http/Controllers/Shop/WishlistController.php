<?php

namespace App\Http\Controllers\Shop;

use App\Enums\UserEventType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\UserEvent;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Danh sách yêu thích. */
class WishlistController extends Controller
{
    public function index(): View
    {
        $products = Product::query()
            ->whereHas('wishlists', fn ($q) => $q->where('user_id', Auth::id()))
            ->with(['promotions', 'variants', 'images'])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->latest('id')
            ->paginate(12);

        return view('shop.wishlist.index', [
            'products' => $products,
        ]);
    }

    public function toggle(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $existing = Wishlist::where('user_id', Auth::id())
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return $this->traLoi(
                $request,
                'Đã bỏ "' . $product->name . '" khỏi danh sách yêu thích.',
                active: false,
            );
        }

        Wishlist::create([
            'user_id' => Auth::id(),
            'product_id' => $product->id,
        ]);

        UserEvent::log(UserEventType::Wishlist, $request, [
            'product_id' => $product->id,
            'category_id' => $product->category_id,
        ]);

        return $this->traLoi(
            $request,
            'Đã thêm "' . $product->name . '" vào danh sách yêu thích.',
            active: true,
        );
    }

    private function traLoi(Request $request, string $message, bool $active): RedirectResponse|JsonResponse
    {
        if (! $request->expectsJson()) {
            return back()->with('success', $message);
        }

        return response()->json([
            'ok' => true,
            'message' => $message,
            'active' => $active,
        ]);
    }
}
