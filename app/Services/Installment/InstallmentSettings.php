<?php

namespace App\Services\Installment;

use App\Models\Setting;

/**
 * Cấu hình trả góp — ADMIN SỬA ĐƯỢC ở trang "Trả góp", không khoá cứng.
 * ============================================================
 * Mặc định dưới đây là cấu hình khởi đầu. Kế hoạch đã tạo CHỤP lại số kỳ,
 * số ngày mỗi kỳ, ân hạn và % trả trước, nên sửa ở đây chỉ áp cho đơn mới.
 *
 * HAI MỨC theo điểm tín dụng (xem CreditScore):
 *   - dưới "điểm tối thiểu": không được trả góp;
 *   - từ "điểm tối thiểu": mức thường — ít kỳ hơn, trả trước nhiều hơn;
 *   - từ "điểm tốt": mức tốt — nhiều kỳ hơn, trả trước ít hơn.
 */
final class InstallmentSettings
{
    public const MAC_DINH = [
        'tra_gop.bat' => '1',
        'tra_gop.don_toi_thieu' => '1000000',
        'tra_gop.so_ngay_moi_ky' => '14',
        'tra_gop.ngay_an_han' => '3',
        'tra_gop.diem_toi_thieu' => '50',
        'tra_gop.diem_tot' => '70',
        'tra_gop.ky_toi_da_thuong' => '2',
        'tra_gop.ky_toi_da_tot' => '4',
        'tra_gop.tra_truoc_thuong' => '50',
        'tra_gop.tra_truoc_tot' => '30',
    ];

    public static function get(string $khoa): string
    {
        $giaTri = Setting::get($khoa);

        return ($giaTri === null || $giaTri === '') ? self::MAC_DINH[$khoa] : (string) $giaTri;
    }

    public static function soNguyen(string $khoa): int
    {
        return (int) self::get($khoa);
    }

    public static function bat(): bool
    {
        return self::get('tra_gop.bat') === '1';
    }

    public static function donToiThieu(): string
    {
        return bcadd(self::get('tra_gop.don_toi_thieu'), '0', 2);
    }

    /** @return array<string, string> */
    public static function tatCa(): array
    {
        $ra = [];

        foreach (array_keys(self::MAC_DINH) as $khoa) {
            $ra[$khoa] = self::get($khoa);
        }

        return $ra;
    }

    /** @param array<string, int|string> $duLieu dữ liệu ĐÃ kiểm, đánh khoá như MAC_DINH */
    public static function luu(array $duLieu): void
    {
        foreach (array_keys(self::MAC_DINH) as $khoa) {
            if (array_key_exists($khoa, $duLieu)) {
                Setting::set($khoa, (string) $duLieu[$khoa]);
            }
        }
    }
}
