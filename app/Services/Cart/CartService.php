<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Mọi thao tác với giỏ hàng đi qua đây. */
class CartService
{
    public static function toiDaMoiMon(): int
    {
        return \App\Services\Shop\ThamSoKinhDoanh::so('kinh_doanh.gio_toi_da_moi_mon');
    }

    private ?Cart $resolved = null;

    private ?int $lastClamped = null;

    public function current(): Cart
    {
        if ($this->resolved) {
            return $this->resolved;
        }

        $cart = Auth::check()
            ? Cart::firstOrCreate(['user_id' => Auth::id()])
            : Cart::firstOrCreate(['session_id' => session()->getId()]);

        return $this->resolved = $cart->load([
            'items.product.category',
            'items.product.promotions',
            'items.variant',
        ]);
    }

    public function mergeSessionCartInto(int $userId, string $sessionId): void
    {
        $guestCart = Cart::where('session_id', $sessionId)->with('items')->first();

        if (! $guestCart || $guestCart->items->isEmpty()) {
            $guestCart?->delete();

            return;
        }

        DB::transaction(function () use ($guestCart, $userId) {
            $userCart = Cart::firstOrCreate(['user_id' => $userId]);

            foreach ($guestCart->items as $item) {
                $existing = CartItem::where('cart_id', $userCart->id)
                    ->where('product_id', $item->product_id)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'quantity' => min(self::toiDaMoiMon(), $existing->quantity + $item->quantity),
                    ]);

                    continue;
                }

                $item->update(['cart_id' => $userCart->id]);
            }

            $guestCart->delete();
        });

        $this->resolved = null;
    }

    public function add(Product $product, int $quantity = 1, ?ProductVariant $variant = null): CartItem
    {
        $this->assertPurchasable($product, $variant);

        $quantity = max(1, $quantity);
        $cart = $this->current();

        $item = CartItem::firstOrNew([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
        ]);

        $wanted = ($item->quantity ?? 0) + $quantity;
        $item->quantity = $this->clampToStock($wanted, $product, $variant);
        $item->save();

        $this->lastClamped = $item->quantity < $wanted
            ? $item->quantity
            : null;

        $this->resolved = null;

        return $item;
    }

    public function lastClampedTo(): ?int
    {
        return $this->lastClamped;
    }

    public function updateQuantity(CartItem $item, int $quantity): void
    {
        if ($quantity < 1) {
            $this->remove($item);

            return;
        }

        $item->update([
            'quantity' => $this->clampToStock($quantity, $item->product, $item->variant),
        ]);

        $this->resolved = null;
    }

    public function remove(CartItem $item): void
    {
        $item->delete();
        $this->resolved = null;
    }

    public function clear(): void
    {
        $this->current()->items()->delete();
        $this->resolved = null;
    }

    public function clearSelected(): int
    {
        $deleted = $this->current()->items()->where('is_selected', true)->delete();
        $this->resolved = null;

        return $deleted;
    }

    public function toggleSelection(CartItem $item, bool $selected): bool
    {
        if ($item->cart_id !== $this->current()->id) {
            return false;
        }

        $item->forceFill(['is_selected' => $selected])->save();
        $this->resolved = null;

        return true;
    }

    public function setSelection(array $selectedIds): void
    {
        $cart = $this->current();
        $ids = array_map('intval', $selectedIds);

        $cart->items()->whereIn('id', $ids ?: [0])->update(['is_selected' => true]);
        $cart->items()->whereNotIn('id', $ids ?: [0])->update(['is_selected' => false]);

        $this->resolved = null;
    }

    public function selectedCount(): int
    {
        return $this->current()->items()->where('is_selected', true)->count();
    }

    public function count(): int
    {
        return (int) CartItem::whereHas('cart', function ($q) {
            Auth::check()
                ? $q->where('user_id', Auth::id())
                : $q->where('session_id', session()->getId());
        })->sum('quantity');
    }

    public function assertPurchasable(Product $product, ?ProductVariant $variant): void
    {
        if ($product->price()->isContactForPrice()) {
            throw new CartException('Sản phẩm này chỉ nhận yêu cầu báo giá, không bán trực tiếp qua giỏ hàng.');
        }

        if ($product->status !== 'active') {
            throw new CartException('Sản phẩm hiện không còn được bán.');
        }

        if ($variant && $variant->product_id !== $product->id) {
            throw new CartException('Phiên bản sản phẩm không hợp lệ.');
        }

        if ($variant && ! $variant->is_active) {
            throw new CartException('Phiên bản này hiện không còn được bán.');
        }

        if ($variant === null && $product->variants()->where('is_active', true)->exists()) {
            throw new CartException(
                'Sản phẩm này có nhiều quy cách. Vui lòng chọn quy cách trước khi mua.'
            );
        }

        if ($this->stockOf($product, $variant) === 0) {
            throw new CartException('Sản phẩm đã hết hàng.');
        }
    }

    private function stockOf(Product $product, ?ProductVariant $variant): ?int
    {
        if ($variant) {
            return $variant->track_inventory ? (int) $variant->stock_quantity : null;
        }

        return $product->track_inventory ? (int) $product->stock_quantity : null;
    }

    private function clampToStock(int $wanted, Product $product, ?ProductVariant $variant): int
    {
        $stock = $this->stockOf($product, $variant);
        $limit = $stock === null ? self::toiDaMoiMon() : min($stock, self::toiDaMoiMon());

        return max(1, min($wanted, $limit));
    }
}
