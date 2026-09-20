<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\Catalog\StockAlertService;
use App\Services\Search\ProductSearchIndexer;

/** Giữ chỉ mục tìm kiếm của sản phẩm luôn khớp với dữ liệu. */
class ProductObserver
{
    public function __construct(
        private readonly ProductSearchIndexer $indexer,
    ) {
    }

    public function saving(Product $product): void
    {
        $this->indexer->fill($product);
    }

    public function saved(Product $product): void
    {
        $this->indexer->bumpVersion();
    }

    /** Tồn kho vừa từ 0 lên, hoặc sản phẩm mở bán lại → báo người đang chờ. */
    public function updated(Product $product): void
    {
        $vuaCo = ($product->wasChanged('stock_quantity') && (int) $product->getOriginal('stock_quantity') <= 0)
            || ($product->wasChanged('status') && $product->status === 'active')
            || ($product->wasChanged('track_inventory') && ! $product->track_inventory);

        if ($vuaCo) {
            app(StockAlertService::class)->hangVe($product);
        }
    }

    public function deleted(Product $product): void
    {
        $this->indexer->bumpVersion();
    }

    public function restored(Product $product): void
    {
        $this->indexer->bumpVersion();
    }
}
