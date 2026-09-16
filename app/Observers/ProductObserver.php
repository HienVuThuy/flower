<?php

namespace App\Observers;

use App\Models\Product;
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

    public function deleted(Product $product): void
    {
        $this->indexer->bumpVersion();
    }

    public function restored(Product $product): void
    {
        $this->indexer->bumpVersion();
    }
}
