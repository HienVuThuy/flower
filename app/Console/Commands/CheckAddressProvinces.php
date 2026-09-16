<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Services\Shop\Provinces;
use Illuminate\Console\Command;

/** Soát sổ địa chỉ xem còn tên tỉnh nào ngoài danh sách hiện hành. */
class CheckAddressProvinces extends Command
{
    protected $signature = 'addresses:check-provinces';

    protected $description = 'Liệt kê địa chỉ trong sổ địa chỉ có tỉnh/thành ngoài danh sách hiện hành';

    public function handle(): int
    {
        $total = Address::count();

        if ($total === 0) {
            $this->info('Sổ địa chỉ đang trống, không có gì để soát.');

            return self::SUCCESS;
        }

        $stale = [];

        Address::query()
            ->select(['id', 'user_id', 'label', 'province'])
            ->chunkById(200, function ($rows) use (&$stale) {
                foreach ($rows as $row) {
                    if (! Provinces::isValid($row->province)) {
                        $stale[] = $row;
                    }
                }
            });

        $this->line("Đã soát {$total} địa chỉ.");

        if ($stale === []) {
            $this->info('Tất cả đều dùng tên tỉnh/thành trong danh sách hiện hành.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn(count($stale).' địa chỉ mang tên tỉnh không còn trong danh sách:');

        foreach ($stale as $row) {
            $this->line(sprintf(
                '  #%-5d người dùng %-5d  %-20s %s',
                $row->id,
                $row->user_id,
                $row->label?->label() ?? '(không đặt tên)',
                $row->province ?: '(bỏ trống)',
            ));
        }

        $this->newLine();
        $this->line('Cách xử lý: liên hệ khách để họ tự chọn lại, hoặc sửa tay trong');
        $this->line('trang quản trị. KHÔNG nên đổi hàng loạt — một tỉnh cũ có thể đã');
        $this->line('được chia về nhiều tỉnh mới tuỳ theo huyện.');

        return self::SUCCESS;
    }
}
