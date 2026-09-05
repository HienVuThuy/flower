<?php

namespace App\Console\Commands;

use App\Services\Shipping\GHNService;
use Illuminate\Console\Command;

/**
 * Tra mã địa giới của GHN từ dòng lệnh.
 * ============================================================
 * VÌ SAO CẦN: `GHN_FROM_DISTRICT_ID` trong .env là mã quận/huyện nơi
 * cửa hàng GỬI hàng. Không có mã đó thì không tính được cước, mà GHN
 * không có trang tra cứu — chỉ có API.
 *
 * DỮ LIỆU GHN CÓ BẢN GHI TRÙNG TÊN, và đây là lý do lệnh này in ra CẢ
 * SỐ QUẬN/HUYỆN của mỗi tỉnh. Ví dụ thật gặp phải khi dựng tính năng
 * này: danh sách tỉnh có hai mục tên gần giống nhau —
 *
 *     id=2002  "Hà Nội 02"   → 0 quận/huyện  (bản ghi rác)
 *     id=201   "Hà Nội"      → 30 quận/huyện (bản ghi thật)
 *
 * Chọn nhầm bản đầu thì mọi lời gọi tiếp theo trả về `data: null` kèm
 * `code: 200` — nghĩa là "thành công" nhưng rỗng, và không có gì gợi ý
 * rằng mình đã chọn sai tỉnh. Cột số quận/huyện làm chuyện đó lộ ra
 * ngay.
 */
class GhnLookup extends Command
{
    protected $signature = 'ghn:tra-dia-chi
                            {tinh? : Tên tỉnh/thành cần tra (bỏ trống để liệt kê tất cả)}
                            {--quan= : Tên quận/huyện, để tra tiếp phường/xã}';

    protected $description = 'Tra mã tỉnh/quận/phường của GHN để điền vào .env';

    public function handle(GHNService $ghn): int
    {
        if (! $ghn->configured()) {
            $this->error('Chưa khai GHN_TOKEN và GHN_SHOP_ID trong .env.');

            return self::FAILURE;
        }

        $provinces = $ghn->getProvinces();

        if (($provinces['code'] ?? null) !== 200) {
            $this->error('Không gọi được GHN: '.($provinces['message'] ?? 'không rõ lý do'));

            return self::FAILURE;
        }

        $tuKhoa = $this->argument('tinh');

        $danhSach = collect($provinces['data'] ?? [])
            ->when($tuKhoa, fn ($c) => $c->filter(
                fn ($p) => str_contains(
                    mb_strtolower($p['ProvinceName']),
                    mb_strtolower($tuKhoa),
                ),
            ))
            ->values();

        if ($danhSach->isEmpty()) {
            $this->warn('Không tìm thấy tỉnh/thành nào khớp "'.$tuKhoa.'".');

            return self::SUCCESS;
        }

        /* ---------- Không nêu quận: liệt kê tỉnh ---------- */

        if (! $this->option('quan')) {
            $this->table(
                ['ProvinceID', 'Tên tỉnh/thành', 'Số quận/huyện'],
                $danhSach->map(function ($p) use ($ghn) {
                    $d = $ghn->getDistricts((int) $p['ProvinceID']);

                    return [
                        $p['ProvinceID'],
                        $p['ProvinceName'],
                        is_array($d['data'] ?? null) ? count($d['data']) : 0,
                    ];
                })->all(),
            );

            $this->newLine();
            $this->line('Số quận/huyện bằng 0 nghĩa là BẢN GHI RÁC — đừng dùng mã đó.');
            $this->line('Tra tiếp quận/huyện:  php artisan ghn:tra-dia-chi "'.($tuKhoa ?: 'Hà Nội').'" --quan="Bắc Từ Liêm"');

            return self::SUCCESS;
        }

        /* ---------- Có nêu quận: tra tới phường/xã ---------- */

        $tenQuan = (string) $this->option('quan');

        foreach ($danhSach as $p) {
            $districts = $ghn->getDistricts((int) $p['ProvinceID']);

            $khop = collect($districts['data'] ?? [])->filter(
                fn ($d) => str_contains(mb_strtolower($d['DistrictName']), mb_strtolower($tenQuan)),
            );

            foreach ($khop as $d) {
                $this->info(sprintf(
                    '%s / %s  →  DistrictID = %d',
                    $p['ProvinceName'],
                    $d['DistrictName'],
                    $d['DistrictID'],
                ));

                $wards = $ghn->getWards((int) $d['DistrictID']);

                $this->table(
                    ['WardCode', 'Tên phường/xã'],
                    collect($wards['data'] ?? [])
                        ->map(fn ($w) => [$w['WardCode'], $w['WardName']])
                        ->all(),
                );
            }
        }

        return self::SUCCESS;
    }
}
