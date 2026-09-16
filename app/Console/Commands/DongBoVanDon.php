<?php

namespace App\Console\Commands;

use App\Services\Shipping\GHNService;
use App\Services\Shipping\GhnStatusSync;
use Illuminate\Console\Command;

/** Hỏi GHN tình trạng mọi vận đơn đang chạy. */
class DongBoVanDon extends Command
{
    protected $signature = 'ghn:dong-bo';

    protected $description = 'Hỏi GHN tình trạng vận đơn, tự hoàn tất đơn đã giao và ghi nhận tiền COD';

    public function handle(GhnStatusSync $sync, GHNService $ghn): int
    {
        if (! $ghn->configured()) {
            $this->warn('Chưa cấu hình GHN (thiếu token hoặc shop_id) — bỏ qua.');

            return self::SUCCESS;
        }

        $ketQua = $sync->syncAll();

        $this->info(sprintf(
            'Đã hỏi %d vận đơn, %d đơn có thay đổi, %d lỗi.',
            $ketQua['da_hoi'],
            $ketQua['da_doi'],
            $ketQua['loi'],
        ));

        return $ketQua['loi'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
