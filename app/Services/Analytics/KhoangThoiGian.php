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

    /**
     * Múi giờ người dùng đọc báo cáo.
     *
     * Uỷ cho App\Services\Time\Gio — nơi duy nhất khai múi giờ hiển thị,
     * dùng chung với phần in ngày giờ ở mọi trang. Khai hai nơi thì báo
     * cáo gom theo một múi còn trang đơn hiện theo múi khác, và con số
     * trên biểu đồ không khớp với con số người ta đếm bằng tay.
     */
    public static function muiGio(): string
    {
        return \App\Services\Time\Gio::mui();
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

    /**
     * Áp khoảng lên một cột KIỂU NGÀY (DATE), không phải mốc thời gian.
     * ============================================================
     * VÌ SAO PHẢI CÓ HÀM RIÊNG.
     *
     * `$tu` và `$den` là mốc GIỜ LƯU (UTC). So một cột DATE với chúng thì
     * cơ sở dữ liệu nâng ngày thành `00:00:00` rồi so.
     *
     * Với giờ Việt Nam (+7) việc đó TÌNH CỜ ra đúng: nửa đêm 13/09 Hà Nội
     * là `12/09 17:00` UTC, và `12/09 00:00` < 17:00 < `13/09 00:00` — ngày
     * 12 bị loại, ngày 13 được lấy, đúng ý. Nhưng nó đúng NHỜ múi giờ
     * dương, không nhờ logic. Với múi giờ âm (ví dụ New York, −4) nửa đêm
     * 13/09 là `13/09 04:00` UTC, và `13/09 00:00` < 04:00 — NGÀY ĐẦU KỲ
     * BỊ LOẠI mà không có lỗi nào hiện ra.
     *
     * (Ghi chú trung thực: bản đầu của chú thích này nói apDung() "sai 7
     * tiếng" với giờ Việt Nam. Thử phá code cho thấy câu đó sai — bài kiểm
     * thử viết theo giờ Việt Nam không phân biệt được hai hàm. Bài kiểm
     * thử hiện tại dùng múi giờ âm.)
     *
     * Cột DATE không mang giờ và cũng không cần: `purchased_at` là "ngày
     * đi lấy hàng", một con số người ta viết trên tờ giấy. Nên đổi mốc về
     * NGÀY ĐỊA PHƯƠNG rồi so chuỗi ngày với chuỗi ngày.
     *
     * Mốc kết thúc vẫn MỞ: `$den` là nửa đêm của ngày SAU ngày cuối, nên
     * `< ngày($den)` lấy đúng tới hết ngày cuối, không lố sang kỳ sau.
     *
     * @param  string  $cot  tên cột ngày, có tiền tố bảng khi cần join
     */
    public function apDungNgay(mixed $query, string $cot): mixed
    {
        if ($this->tu) {
            $query->where($cot, '>=', self::diaPhuong($this->tu)->toDateString());
        }

        if ($this->den) {
            $query->where($cot, '<', self::diaPhuong($this->den)->toDateString());
        }

        return $query;
    }
}
