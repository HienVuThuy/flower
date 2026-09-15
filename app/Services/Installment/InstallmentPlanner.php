<?php

namespace App\Services\Installment;

use Carbon\CarbonImmutable;

/**
 * Chia số tiền thành lịch trả — phép tính thuần, không đọc cơ sở dữ liệu.
 * ============================================================
 *   - Trả trước = làm tròn LÊN tới đồng của (tổng × % trả trước), hạn hôm nay.
 *   - Phần còn lại chia đều cho n kỳ, làm tròn XUỐNG; kỳ cuối nhận phần dư,
 *     nên tổng các kỳ luôn đúng bằng tổng đơn, không lệch một đồng.
 *   - Kỳ k hạn = ngày đầu + k × số ngày mỗi kỳ.
 */
final class InstallmentPlanner
{
    /**
     * @return list<array{sequence: int, amount: string, due_on: string}>
     */
    public static function lich(string $tong, int $soKy, int $traTruocPhanTram, string $ngayDau, int $soNgayMoiKy): array
    {
        $tongDong = (int) bcadd($tong, '0', 0);
        // Phần lẻ dưới một đồng (không có ở VND, nhưng đừng để nó biến mất) dồn vào kỳ cuối.
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
