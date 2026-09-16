<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\Search\ProductSearchIndexer;

/** Đổi tên danh mục thì chỉ mục của mọi sản phẩm trong đó phải dựng lại. */
class CategoryObserver
{
    public function __construct(
        private readonly ProductSearchIndexer $indexer,
    ) {
    }

    public function saved(Category $category): void
    {
        if ($category->wasChanged('name')) {
            $this->indexer->reindexCategory($category);
        }
    }
}
