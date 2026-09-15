<?php

namespace App\Console\Commands;

use App\Services\Installment\InstallmentService;
use Illuminate\Console\Command;

class XuLyTraGopQuaHan extends Command
{
    protected $signature = 'tra-gop:qua-han';

    protected $description = 'Huỷ các kế hoạch trả góp có kỳ quá hạn vượt số ngày ân hạn (hoàn kho, ghi khoản phải hoàn)';

    public function handle(InstallmentService $traGop): int
    {
        $so = $traGop->xuLyQuaHan();

        $this->info($so === 0 ? 'Không có kế hoạch trả góp nào quá hạn.' : "Đã huỷ {$so} kế hoạch trả góp quá hạn.");

        return self::SUCCESS;
    }
}
