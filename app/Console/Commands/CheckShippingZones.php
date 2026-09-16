<?php

namespace App\Console\Commands;

use App\Services\Shipping\ShippingRates;
use App\Services\Shop\Provinces;
use Illuminate\Console\Command;

/** In bảng phí giao theo vùng và soát lệch giữa hai tệp config. */
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

        $unknown = array_diff($mapped, $all);

        if ($unknown !== []) {
            $this->error('Tên KHÔNG có trong config/provinces.php (sẽ không bao giờ khớp):');

            foreach ($unknown as $name) {
                $this->line('  - '.$name);
            }
        } else {
            $this->info('Mọi tên tỉnh trong bảng vùng đều khớp config/provinces.php.');
        }

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

        return $unknown === [] ? self::SUCCESS : self::FAILURE;
    }
}
