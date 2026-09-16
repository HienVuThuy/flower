<?php

namespace App\Services\Installment;

use Carbon\CarbonImmutable;

/** Chia số tiền thành lịch trả — phép tính thuần, không đọc cơ sở dữ liệu. */
final class InstallmentPlanner
{
    public static function lich(string $tong, int $soKy, int $traTruocPhanTram, string $ngayDau, int $soNgayMoiKy): array
    {
        $tongDong = (int) bcadd($tong, '0', 0);
        $le = bcsub($tong, (string) $tongDong, 2);

        $traTruoc = intdiv($tongDong * $traTruocPhanTram + 99, 100);
        $conLai = $tongDong - $traTruoc;
        $moiKy = intdiv($conLai, $soKy);
        $dau = CarbonImmutable::parse($ngayDau);

        $lich = [[
            'sequence' => 0,
            'amount' => bcadd((string) $traTruoc, '0', 2),
            'due_on' => $dau->toDateString(),
        ]];

        for ($k = 1; $k <= $soKy; $k++) {
            $soTien = $k < $soKy ? $moiKy : $conLai - $moiKy * ($soKy - 1);

            $lich[] = [
                'sequence' => $k,
                'amount' => bcadd((string) $soTien, $k === $soKy ? $le : '0', 2),
                'due_on' => $dau->addDays($k * $soNgayMoiKy)->toDateString(),
            ];
        }

        return $lich;
    }
}
