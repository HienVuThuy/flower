<?php

namespace App\Console\Commands;

use App\Services\Search\ProductSearchIndexer;
use Illuminate\Console\Command;

/**
 * Dựng lại chỉ mục tìm kiếm cho toàn bộ sản phẩm.
 *
 * BA LÚC CẦN CHẠY:
 *   1. Ngay sau khi chạy migration thêm hai cột — dữ liệu cũ chưa có
 *      chỉ mục, không chạy thì mọi sản phẩm hiện có đều không tìm được.
 *   2. Sau khi sửa công thức trong ProductSearchIndexer::values().
 *   3. Sau khi nạp dữ liệu thẳng vào cơ sở dữ liệu bằng SQL (import,
 *      khôi phục bản sao lưu) — đường đó không đi qua model nên observer
 *      không bắn.
 *
 * Chạy lại bao nhiêu lần cũng được: lệnh chỉ ghi những dòng thật sự lệch.
 */
class ReindexSearch extends Command
{
    protected $signature = 'search:reindex';

    protected $description = 'Dựng lại chỉ mục tìm kiếm (search_name, search_text) cho sản phẩm';

    public function handle(ProductSearchIndexer $indexer): int
    {
        $this->info('Đang dựng lại chỉ mục tìm kiếm...');

        $result = $indexer->reindexAll();

        $this->line("Đã quét {$result['scanned']} sản phẩm, cập nhật {$result['written']}.");

        if ($result['written'] === 0 && $result['scanned'] > 0) {
            $this->line('Chỉ mục vốn đã đúng, không cần ghi gì thêm.');
        }

        return self::SUCCESS;
    }
}
