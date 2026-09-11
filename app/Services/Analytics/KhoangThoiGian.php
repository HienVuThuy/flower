<?php

namespace App\Services\Analytics;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Một khoảng thời gian báo cáo, và cách đọc giờ cho đúng.
 * ============================================================
 * VÌ SAO TÁCH RA: trang Phân tích nay có nhiều trang con (doanh thu, khách
 * hàng, đánh giá, lợi nhuận), mỗi trang một lớp tính riêng, nhưng tất cả
 * phải nói về CÙNG MỘT KỲ. Mỗi lớp tự viết `where created_at >= ...` là
 * mỗi lớp một cách hiểu "7 ngày qua" — một nơi quên mốc kết thúc là số
 * của kỳ trước lẫn vào kỳ này, và không có gì báo.
 *
 * ============================================================
 * GIỜ VIỆT NAM, KHÔNG PHẢI GIỜ LƯU.
 *
 * Ứng dụng chạy `app.timezone = UTC`: một đơn đặt lúc 15:52 giờ Hà Nội
 * được lưu là 08:52. Đo trên dữ liệu thật — trang đơn hiện đúng 08:52.
 * Hệ quả cho báo cáo:
 *
 *   - "khung giờ khách đặt hàng" lệch nguyên 7 tiếng;
 *   - "doanh thu theo ngày" gom mọi đơn từ 0h tới 7h sáng vào NGÀY HÔM
 *     TRƯỚC;
 *   - "7 ngày qua" bắt đầu lúc 7h sáng chứ không phải nửa đêm.
 *
 * KHÔNG đổi `app.timezone`: dữ liệu cũ đã lưu theo UTC trong cột DATETIME
 * không mang múi giờ, đổi cấu hình là mọi mốc cũ bị đọc lệch 7 tiếng.
 * Lưu theo UTC vẫn đúng; chỉ có phần GOM NHÓM và CẮT MỐC là phải quy về
 * giờ địa phương — và việc đó làm ở đây, một chỗ.
 */
final class KhoangThoiGian
{
    public function __construct(
        public readonly ?Carbon $tu = null,
        public readonly ?Carbon $den = null,
    ) {
    }

    /** Múi giờ người dùng đọc báo cáo. */
    public static function muiGio(): string
    {
        return (string) config('app.display_timezone', 'Asia/Ho_Chi_Minh');
    }

    /** Đổi một mốc đã lưu sang giờ địa phương để gom nhóm hoặc hiển thị. */
    public static function diaPhuong(CarbonInterface $moc): Carbon
    {
        return Carbon::instance($moc)->setTimezone(self::muiGio());
    }

    /**
     * Nửa đêm (giờ địa phương) của n ngày trước, trả về THEO GIỜ LƯU.
     *
     * Phải trả về theo giờ lưu: Eloquent đưa Carbon vào truy vấn bằng
     * `format('Y-m-d H:i:s')`, KHÔNG đổi múi giờ. Truyền thẳng một mốc giờ
     * Hà Nội vào là so sánh lệch 7 tiếng mà không có lỗi nào hiện ra.
     */
    public static function nuaDemTruoc(int $soNgay): Carbon
    {
        return now(self::muiGio())
            ->subDays($soNgay)
            ->startOfDay()
            ->setTimezone((string) config('app.timezone'));
    }

    /**
     * Áp khoảng lên một câu truy vấn.
     *
     * `<` chứ không phải `<=` ở mốc kết thúc: mốc kết thúc của kỳ trước
     * CHÍNH LÀ mốc bắt đầu của kỳ này. Dùng `<=` thì bản ghi rơi đúng vào
     * giây đó bị đếm ở cả hai kỳ.
     *
     * @param  string  $cot  tên cột thời gian, có tiền tố bảng khi cần join
     */
    public function apDung(mixed $query, string $cot): mixed
    {
        if ($this->tu) {
            $query->where($cot, '>=', $this->tu);
        }

        if ($this->den) {
            $query->where($cot, '<', $this->den);
        }

        return $query;
    }
}
