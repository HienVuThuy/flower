<?php

namespace App\Observers;

use App\Models\ProductVariant;
use App\Services\Catalog\StockAlertService;

/** Quy cách vừa có hàng lại thì báo người đang chờ. */
class ProductVariantObserver
{
    public function updated(ProductVariant $variant): void
    {
        $vuaCo = ($variant->wasChanged('stock_quantity') && (int) $variant->getOriginal('stock_quantity') <= 0)
            || ($variant->wasChanged('is_active') && $variant->is_active)
            || ($variant->wasChanged('track_inventory') && ! $variant->track_inventory);

        if (! $vuaCo || ! $variant->is_active) {
            return;
        }

        $sanPham = $variant->product;

        if ($sanPham !== null) {
            app(StockAlertService::class)->hangVe($sanPham, $variant);
        }
    }
}
