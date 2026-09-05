<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\Search\ProductSearchIndexer;

/**
 * Đổi tên danh mục thì chỉ mục của mọi sản phẩm trong đó phải dựng lại.
 * ============================================================
 * Tên danh mục nằm TRONG products.search_text — đó là cách khách gõ
 * "cây để bàn" tìm ra hàng dù không sản phẩm nào mang chữ đó trong tên.
 * Cái giá của việc chép dữ liệu sang bảng khác là phải tự đồng bộ, và
 * đây là chỗ trả cái giá đó.
 *
 * Bỏ qua bước này thì lỗi biểu hiện rất muộn và rất khó lần: admin đổi
 * "Cây cảnh" thành "Cây xanh", vài tuần sau mới có người phàn nàn tìm
 * "cây xanh" không ra gì, lúc đó chẳng ai nhớ tới lần đổi tên nữa.
 */
class CategoryObserver
{
    public function __construct(
        private readonly ProductSearchIndexer $indexer,
    ) {
    }

    public function saved(Category $category): void
    {
        /*
         * CHỈ khi tên thật sự đổi.
         *
         * Bật/tắt danh mục hay sửa thứ tự sắp xếp không đụng gì tới chỉ
         * mục. Không có điều kiện này thì mỗi lần bấm Lưu ở form danh mục
         * là ghi lại toàn bộ sản phẩm bên trong — vô ích, và với danh mục
         * lớn thì đó là một lượt ghi hàng nghìn dòng.
         */
        if ($category->wasChanged('name')) {
            $this->indexer->reindexCategory($category);
        }
    }
}
