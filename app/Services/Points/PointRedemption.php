<?php

namespace App\Services\Points;

/**
 * Luật dùng điểm để trừ tiền khi đặt hàng — MỘT CHỖ KHAI.
 * ============================================================
 * 1 ĐIỂM = 100Đ, khớp với gói đổi voucher (200 điểm → 20.000đ). Hai tỉ giá
 * khác nhau cho cùng một điểm thì khách luôn chọn đường lời hơn, và một
 * trong hai con số thành vô nghĩa.
 *
 * TỐI THIỂU 100 ĐIỂM mỗi lần dùng: dưới mức đó số tiền giảm lẻ vài đồng,
 * chỉ làm dòng tóm tắt đơn rối thêm.
 *
 * TỐI ĐA 30% TIỀN HÀNG SAU MÃ GIẢM GIÁ: điểm là phần thưởng, không phải ví
 * tiền. Không có trần thì một tài khoản tích lâu năm mua cây gần như miễn
 * phí, và mọi khuyến mại cộng lại có thể vượt giá vốn.
 *
 * Tính trên tiền hàng SAU mã: mã giảm trước, điểm trừ trên phần còn lại —
 * nếu không, mã 50% cộng điểm 30% của cùng một giá gốc là giảm 80%.
 */
final class PointRedemption
{
    public const DONG_MOI_DIEM = 100;

    public const TOI_THIEU = 100;

    public const PHAN_TRAM_TOI_DA = 30;

    public static function quyRaTien(int $diem): string
    {
        return bcmul((string) max(0, $diem), (string) self::DONG_MOI_DIEM, 2);
    }

    /** Số điểm tối đa dùng được cho số tiền hàng này — chưa xét số dư. */
    public static function toiDaTheoTien(string $tienHang): int
    {
        if (bccomp($tienHang, '0', 2) <= 0) {
            return 0;
        }

        $tienToiDa = bcdiv(bcmul($tienHang, (string) self::PHAN_TRAM_TOI_DA, 2), '100', 2);

        return (int) bcdiv($tienToiDa, (string) self::DONG_MOI_DIEM, 0);
    }

    /**
     * Số điểm thật sự dùng được: không quá số dư, không quá trần theo tiền,
     * và 0 nếu chưa tới mức tối thiểu.
     */
    public static function dungDuoc(int $muonDung, string $tienHang, int $soDu): int
    {
        $n = min(max(0, $muonDung), max(0, $soDu), self::toiDaTheoTien($tienHang));

        return $n >= self::TOI_THIEU ? $n : 0;
    }
}
