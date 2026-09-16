<?php

namespace App\Services\Points;

/** Luật dùng điểm để trừ tiền khi đặt hàng — MỘT CHỖ KHAI. */
final class PointRedemption
{
    public const DONG_MOI_DIEM = 100;

    public const TOI_THIEU = 100;

    public const PHAN_TRAM_TOI_DA = 30;

    public static function quyRaTien(int $diem): string
    {
        return bcmul((string) max(0, $diem), (string) self::DONG_MOI_DIEM, 2);
    }

    public static function toiDaTheoTien(string $tienHang): int
    {
        if (bccomp($tienHang, '0', 2) <= 0) {
            return 0;
        }

        $tienToiDa = bcdiv(bcmul($tienHang, (string) self::PHAN_TRAM_TOI_DA, 2), '100', 2);

        return (int) bcdiv($tienToiDa, (string) self::DONG_MOI_DIEM, 0);
    }

    public static function dungDuoc(int $muonDung, string $tienHang, int $soDu): int
    {
        $n = min(max(0, $muonDung), max(0, $soDu), self::toiDaTheoTien($tienHang));

        return $n >= self::TOI_THIEU ? $n : 0;
    }
}
