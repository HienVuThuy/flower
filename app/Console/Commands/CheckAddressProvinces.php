<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Services\Shop\Provinces;
use Illuminate\Console\Command;

/**
 * Soát sổ địa chỉ xem còn tên tỉnh nào ngoài danh sách hiện hành.
 * ============================================================
 * CHỈ BÁO CÁO, KHÔNG TỰ SỬA — và đó là điểm quan trọng nhất của lệnh này.
 *
 * Sau đợt sắp xếp 2025, một địa chỉ ghi "Hà Giang" phải thành "Tuyên
 * Quang". Nhưng không phải trường hợp nào cũng một-đối-một: có tỉnh bị
 * chia, có tỉnh nhập vào nơi khác nhau tuỳ huyện. Máy tự đoán rồi ghi đè
 * là làm hỏng địa chỉ giao hàng của khách mà không ai biết — hàng đi
 * nhầm nơi và không có cách nào lần lại giá trị cũ.
 *
 * Vì vậy: lệnh liệt kê ra để người thật quyết định, và khách cũng được
 * nhắc ngay trên biểu mẫu (địa chỉ có tỉnh ngoài danh sách hiện ra trong
 * nhóm "không còn trong danh sách hiện hành" và buộc chọn lại khi sửa).
 *
 * KHÔNG ĐỘNG TỚI BẢNG `orders`: cột shipping_province ở đó là BẢN CHỤP
 * tại thời điểm đặt hàng. Đơn giao về "Hà Tây" năm 2007 phải mãi mãi đọc
 * được là "Hà Tây" — viết lại là làm sai lịch sử giao dịch.
 *
 *     php artisan addresses:check-provinces
 */
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
                // `label` được cast sang enum AddressLabel, không phải
                // chuỗi. Nhét thẳng vào sprintf('%s') là lỗi "không
                // chuyển được object sang string" — chỉ nổ đúng lúc có
                // địa chỉ lỗi thời, tức là đúng lúc cần lệnh này nhất.
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
