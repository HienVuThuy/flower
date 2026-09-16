<?php

namespace App\Services\Inventory;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;

/** Cộng lại vào kho số hàng của một dòng đơn. */
class StockReturn
{
    public function congLai(OrderItem $item, int $soLuong): void
    {
        if ($soLuong <= 0) {
            return;
        }

        if ($item->product_variant_id) {
            ProductVariant::whereKey($item->product_variant_id)
                ->where('track_inventory', true)
                ->increment('stock_quantity', $soLuong);

            return;
        }

        if ($item->product_id) {
            Product::whereKey($item->product_id)
                ->where('track_inventory', true)
                ->increment('stock_quantity', $soLuong);

            return;
        }

        if ($item->is_gift && $item->gift_item_id) {
            \App\Models\GiftItem::whereKey($item->gift_item_id)
                ->whereNull('product_id')
                ->increment('stock_quantity', $soLuong);
        }
    }
}
