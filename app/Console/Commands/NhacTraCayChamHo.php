<?php

namespace App\Console\Commands;

use App\Services\Boarding\BoardingService;
use Illuminate\Console\Command;

/** Phiếu chăm hộ sắp đến ngày trả: chuyển sang "Sắp trả cây" và báo khách. */
class NhacTraCayChamHo extends Command
{
    protected $signature = 'cham-ho:nhac';

    protected $description = 'Nhắc các phiếu chăm cây hộ sắp đến ngày trả cây';

    public function handle(BoardingService $dichVu): int
    {
        $this->info('Đã chuyển ' . $dichVu->nhacDenHan() . ' phiếu sang "Sắp trả cây".');

        return self::SUCCESS;
    }
}
