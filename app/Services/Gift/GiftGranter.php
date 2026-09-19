<?php

namespace App\Services\Gift;

use App\Models\GiftItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\User;
use App\Services\Checkout\CheckoutBasket;
use Illuminate\Support\Facades\DB;

/** Ghi quà vào đơn và trả lại khi đơn huỷ. */
class GiftGranter
{
    public function __construct(
        private readonly GiftResolver $resolver,
    ) {
    }

    public function tangChoDon(Order $order, CheckoutBasket $basket, ?User $user): array
    {
        $daTang = [];

        foreach ($this->resolver->choGio($basket, $user) as $dong) {
            $vat = $dong['item'];
            $soLuong = $dong['quantity'];
            $ct = $dong['campaign'];

            if ($ct !== null && ! $this->giuSuat($ct)) {
                continue;
            }

            if (! $this->truKho($vat, $soLuong)) {
                if ($ct !== null) {
                    $this->traSuatMot($ct->id);
                }

                continue;
            }

            $order->items()->create([
                'product_id' => $vat->product_id,
                'product_variant_id' => $vat->product_variant_id,
                'product_name' => $vat->name,
                'product_sku' => $vat->variant?->code ?? $vat->product?->product_code,
                'variant_name' => $vat->variant?->name,
                'promotion_name' => $ct?->name ?? 'Quà miễn phí',
                'unit_base_price' => $vat->value ?? '0.00',
                'unit_price' => '0.00',
                'quantity' => $soLuong,
                'line_total' => '0.00',
                'discount_amount' => '0.00',
                'is_gift' => true,
                'gift_promotion_id' => $ct?->id,
                'product_gift_id' => $dong['product_gift']?->id,
                'gift_item_id' => $vat->id,
                'parent_item_id' => $this->dongCha($order, $dong),
            ]);

            $daTang[] = $vat->name;
        }

        return $daTang;
    }

    public function traSuatCuaDon(Order $order): void
    {
        $order->items()
            ->where('is_gift', true)
            ->whereNotNull('gift_promotion_id')
            ->pluck('gift_promotion_id')
            ->each(fn ($id) => $this->traSuatMot((int) $id));
    }

    private function dongCha(Order $order, array $dong): ?int
    {
        if ($dong['for_product_id'] === null) {
            return null;
        }

        return $order->items()
            ->where('is_gift', false)
            ->where('product_id', $dong['for_product_id'])
            ->when(
                $dong['for_variant_id'] !== null,
                fn ($q) => $q->where('product_variant_id', $dong['for_variant_id']),
                fn ($q) => $q->whereNull('product_variant_id'),
            )
            ->orderBy('id')
            ->value('id');
    }

    private function giuSuat(Promotion $ct): bool
    {
        return Promotion::whereKey($ct->id)
            ->where(fn ($q) => $q->whereNull('total_limit')->orWhereColumn('used_count', '<', 'total_limit'))
            ->update(['used_count' => DB::raw('used_count + 1')]) === 1;
    }

    private function traSuatMot(int $khuyenMaiId): void
    {
        Promotion::whereKey($khuyenMaiId)->where('used_count', '>', 0)->decrement('used_count');
    }

    private function truKho(GiftItem $vat, int $soLuong): bool
    {
        if (! $vat->laSanPham()) {
            return GiftItem::whereKey($vat->id)
                ->where('stock_quantity', '>=', $soLuong)
                ->decrement('stock_quantity', $soLuong) === 1;
        }

        $dong = $vat->product_variant_id !== null
            ? ProductVariant::whereKey($vat->product_variant_id)->lockForUpdate()->first()
            : Product::whereKey($vat->product_id)->lockForUpdate()->first();

        if ($dong === null) {
            return false;
        }

        if (! $dong->track_inventory) {
            return true;
        }

        if ((int) $dong->stock_quantity < $soLuong) {
            return false;
        }

        $dong->decrement('stock_quantity', $soLuong);

        return true;
    }
}
