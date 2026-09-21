<?php

namespace App\Services\Points;

/** Luật dùng điểm để trừ tiền khi đặt hàng — MỘT CHỖ KHAI. */
final class PointRedemption
{
    public static function dongMoiDiem(): int
    {
        return \App\Services\Shop\ThamSoKinhDoanh::so('kinh_doanh.diem.dong_moi_diem_dung');
    }

    public static function toiThieu(): int
    {
        return \App\Services\Shop\ThamSoKinhDoanh::so('kinh_doanh.diem.dung_toi_thieu');
    }

    public static function phanTramToiDa(): int
    {
        return \App\Services\Shop\ThamSoKinhDoanh::so('kinh_doanh.diem.phan_tram_toi_da');
    }

    public static function quyRaTien(int $diem): string
    {
        return bcmul((string) max(0, $diem), (string) self::dongMoiDiem(), 2);
    }

    public static function toiDaTheoTien(string $tienHang): int
    {
        if (bccomp($tienHang, '0', 2) <= 0) {
            return 0;
        }

        $tienToiDa = bcdiv(bcmul($tienHang, (string) self::phanTramToiDa(), 2), '100', 2);

        return (int) bcdiv($tienToiDa, (string) self::dongMoiDiem(), 0);
    }

    public static function dungDuoc(int $muonDung, string $tienHang, int $soDu): int
    {
        $n = min(max(0, $muonDung), max(0, $soDu), self::toiDaTheoTien($tienHang));

        return $n >= self::toiThieu() ? $n : 0;
    }
}
