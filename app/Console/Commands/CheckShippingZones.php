<?php

namespace App\Console\Commands;

use App\Services\Shipping\ShippingRates;
use App\Services\Shop\Provinces;
use Illuminate\Console\Command;

/**
 * In bảng phí giao theo vùng và soát lệch giữa hai tệp config.
 *
 * VÌ SAO CẦN MỘT LỆNH RIÊNG:
 * config/shipping.php nhắc tên tỉnh bằng chuỗi, còn danh sách tỉnh thật
 * nằm ở config/provinces.php. Không có gì buộc hai bên khớp nhau, và khi
 * lệch thì KHÔNG có lỗi nào xảy ra — tỉnh gõ sai chỉ lặng lẽ rơi vào vùng
 * mặc định và khách bị tính sai phí.
 *
 * Đợt sắp xếp 2025 vừa bỏ tỉnh Hà Giang là ví dụ có thật: một dòng trong
 * bảng vùng trỏ tới cái tên không còn tồn tại. Lệnh này tìm ra đúng loại
 * đó trong một giây.
 *
 *     php artisan shipping:zones
 */
class CheckShippingZones extends Command
{
    protected $signature = 'shipping:zones';

    protected $description = 'In bảng phí giao theo vùng và soát tên tỉnh lệch với config/provinces.php';

    public function handle(ShippingRates $rates): int
    {
        $all = Provinces::all();
        $mapped = array_keys(config('shipping.zone_of', []));

        $this->line('Ngưỡng miễn phí giao: '.number_format((float) $rates->freeFrom(), 0, ',', '.').'đ');
        $this->newLine();

        foreach ($rates->zones() as $key => $zone) {
            $this->line(sprintf(
                '%-8s %-38s %10sđ  (%d tỉnh khai riêng)',
                $key,
                $zone['label'],
                number_format($zone['fee'], 0, ',', '.'),
                count($zone['provinces']),
            ));
        }

        $this->newLine();

        /* ---- Lệch 1: tên trong bảng vùng không có trong danh sách tỉnh ---- */
        $unknown = array_diff($mapped, $all);

        if ($unknown !== []) {
            $this->error('Tên KHÔNG có trong config/provinces.php (sẽ không bao giờ khớp):');

            foreach ($unknown as $name) {
                $this->line('  - '.$name);
            }
        } else {
            $this->info('Mọi tên tỉnh trong bảng vùng đều khớp config/provinces.php.');
        }

        /* ---- Lệch 2: tỉnh chưa khai, đang dùng vùng mặc định ---- */
        $default = array_diff($all, $mapped);

        if ($default !== []) {
            $this->newLine();
            $this->warn(sprintf(
                '%d tỉnh chưa khai vùng riêng, đang tính theo vùng mặc định "%s" (%sđ):',
                count($default),
                config('shipping.default_zone'),
                number_format((float) $rates->feeFor(null), 0, ',', '.'),
            ));

            foreach ($default as $name) {
                $this->line('  - '.$name);
            }
        }

        // Lệch loại 1 là lỗi cấu hình thật, phải báo về mã thoát khác 0 để
        // còn dùng được trong kịch bản kiểm tra tự động.
        return $unknown === [] ? self::SUCCESS : self::FAILURE;
    }
}
