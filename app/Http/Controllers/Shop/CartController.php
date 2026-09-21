<?php

namespace App\Http\Controllers\Shop;

use App\Enums\UserEventType;
use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UserEvent;
use App\Services\Cart\CartException;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutSource;
use App\Services\Recommendation\PlantAdvisor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly CheckoutSource $checkout,
        private readonly PlantAdvisor $advisor,
    ) {
    }

    public function index(): RedirectResponse|View
    {
        if ($this->checkout->hasDirect()) {
            $this->checkout->clearDirect();

            return redirect()
                ->route('shop.cart.index')
                ->with('info', 'Đã huỷ lượt "Mua ngay" vì bạn quay lại giỏ hàng. Giỏ hàng của bạn vẫn nguyên vẹn.');
        }

        return view('shop.cart.index', $this->duLieuGio());
    }

    public function fragment(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'cartCount' => $this->cart->count(),
            'html' => view('shop.cart.partials.noi-dung', $this->duLieuGio())->render(),
        ]);
    }

    private function duLieuGio(): array
    {
        $cart = $this->cart->current();
        $basket = $this->checkout->cartBasket();

        return [
            'cart' => $cart,
            'basket' => $basket,

            'quaTheoDong' => app(\App\Services\Gift\GiftResolver::class)->theoDong($basket),

            'accessories' => $this->advisor->accessoriesForBasket(
                $cart->items->pluck('product')->filter(),
            ),

            'selectedCount' => $cart->items->filter(fn ($i) => $i->is_selected !== false)->count(),
        ];
    }

    private function traLoiGio(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if (! $request->expectsJson()) {
            return back()->with('success', $message);
        }

        return response()->json([
            'ok' => true,
            'message' => $message,
            'cartCount' => $this->cart->count(),
            'html' => view('shop.cart.partials.noi-dung', $this->duLieuGio())->render(),
        ]);
    }

    public function select(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'selected' => ['nullable', 'array'],
            'selected.*' => ['integer'],
        ]);

        $this->cart->setSelection($validated['selected'] ?? []);

        if (! $request->expectsJson()) {
            return back();
        }

        return $this->traLoiGio($request, 'Đã cập nhật lựa chọn.');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validatePayload($request);

        try {
            $this->cart->add(
                $data['product'],
                $data['quantity'],
                $data['variant'],
            );
        } catch (CartException $e) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $e->getMessage()], 422)
                : back()->with('error', $e->getMessage());
        }

        $this->logCartEvent(UserEventType::AddToCart, $request, $data);

        $clamped = $this->cart->lastClampedTo();

        $message = $clamped === null
            ? 'Đã thêm vào giỏ hàng.'
            : sprintf('Chỉ còn %d sản phẩm nên giỏ hàng nhận %d.', $clamped, $clamped);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,

                'cartCount' => $this->cart->count(),

                'clamped' => $clamped !== null,
            ]);
        }

        return back()->with($clamped === null ? 'success' : 'info', $message);
    }

    public function buyNow(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        try {
            $this->cart->assertPurchasable($data['product'], $data['variant']);
        } catch (CartException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->checkout->setDirect(
            $data['product'],
            $data['quantity'],
            $data['variant'],
        );

        $this->logCartEvent(UserEventType::BuyNow, $request, $data);

        return redirect()->route('shop.checkout.details');
    }

    public function update(Request $request, CartItem $cartItem): RedirectResponse|JsonResponse
    {
        $this->authorizeItem($cartItem);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:' . CartService::toiDaMoiMon()],
        ], [], ['quantity' => 'số lượng']);

        $this->cart->updateQuantity($cartItem, (int) $validated['quantity']);

        return $this->traLoiGio($request, 'Đã cập nhật giỏ hàng.');
    }

    public function destroy(Request $request, CartItem $cartItem): RedirectResponse|JsonResponse
    {
        $this->authorizeItem($cartItem);

        $this->cart->remove($cartItem);

        return $this->traLoiGio($request, 'Đã xoá sản phẩm khỏi giỏ hàng.');
    }

    public function clear(Request $request): RedirectResponse|JsonResponse
    {
        $soMon = $this->cart->current()->items()->count();

        if ($soMon === 0) {
            return $this->traLoiGio($request, 'Giỏ hàng đang trống.');
        }

        $this->cart->clear();

        return $this->traLoiGio($request, 'Đã xoá tất cả sản phẩm khỏi giỏ hàng.');
    }

    private function authorizeItem(CartItem $item): void
    {
        abort_unless($item->cart_id === $this->cart->current()->id, 403);
    }

    private function logCartEvent(UserEventType $type, Request $request, array $data): void
    {
        UserEvent::log($type, $request, [
            'product_id' => $data['product']->id,
            'category_id' => $data['product']->category_id,
            'meta' => [
                'quantity' => $data['quantity'],
                'variant_id' => $data['variant']?->id,
            ],
        ]);
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:' . CartService::toiDaMoiMon()],
        ], [], [
            'product_id' => 'sản phẩm',
            'variant_id' => 'phiên bản',
            'quantity' => 'số lượng',
        ]);

        return [
            'product' => Product::findOrFail($validated['product_id']),
            'variant' => isset($validated['variant_id'])
                ? ProductVariant::find($validated['variant_id'])
                : null,
            'quantity' => (int) ($validated['quantity'] ?? 1),
        ];
    }
}
