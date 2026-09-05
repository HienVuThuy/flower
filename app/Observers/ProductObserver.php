<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\Search\ProductSearchIndexer;

/**
 * Giữ chỉ mục tìm kiếm của sản phẩm luôn khớp với dữ liệu.
 * ============================================================
 * VÌ SAO DÙNG OBSERVER, KHÔNG GỌI TỪ CONTROLLER:
 * Sản phẩm được ghi từ nhiều đường — form admin, seeder, lệnh artisan,
 * và cả tinker lúc sửa dữ liệu tay. Gọi indexer ở từng chỗ thì chỉ cần
 * quên MỘT chỗ là sản phẩm đó biến mất khỏi kết quả tìm kiếm, mà lại
 * không có thông báo lỗi nào — nó vẫn hiện bình thường ở mọi trang khác.
 * Móc vào vòng đời model là chỗ duy nhất không đường nào đi vòng qua được.
 */
class ProductObserver
{
    public function __construct(
        private readonly ProductSearchIndexer $indexer,
    ) {
    }

    /**
     * Chạy ở `saving` chứ không phải `saved`.
     *
     * `saving` xảy ra TRƯỚC khi câu INSERT/UPDATE được gửi đi, nên hai
     * cột chỉ mục đi chung một lượt ghi với phần còn lại. Làm ở `saved`
     * thì phải save() thêm lần nữa: hai lượt ghi, và lượt thứ hai lại
     * kích hoạt chính observer này.
     */
    public function saving(Product $product): void
    {
        $this->indexer->fill($product);
    }

    /**
     * Từ điển gợi ý sửa lỗi gõ được dựng từ toàn bộ catalog, nên bất kỳ
     * thay đổi nào cũng có thể thêm hoặc bớt từ trong đó.
     */
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
