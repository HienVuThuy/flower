<?php

namespace App\Console\Commands;

use App\Services\Search\ProductSearchIndexer;
use Illuminate\Console\Command;

/** Dựng lại chỉ mục tìm kiếm cho toàn bộ sản phẩm. */
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
