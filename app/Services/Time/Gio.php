<?php

namespace App\Services\Time;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Một nơi duy nhất biết "giờ người Việt đọc" khác "giờ đã lưu".
 * ============================================================
 * LỖI ĐANG SỬA: ứng dụng chạy `app.timezone = UTC`, nên mọi mốc lấy ra
 * từ cơ sở dữ liệu là giờ UTC. Đo trên đơn thật: đơn đặt lúc **18:42**
 * giờ Hà Nội hiện ra **11:42** ở trang đơn của khách, trang quản trị,
 * email xác nhận — sớm đúng 7 tiếng, ở mọi chỗ, và không có gì báo.
 *
 * Với khách thì đó là "tôi đâu có đặt lúc đó". Với cửa hàng thì mọi mốc
 * xử lý đơn đều lệch, và giờ ghi trong nhật ký thao tác không khớp với
 * giờ người ta nhớ mình đã bấm.
 *
 * ============================================================
 * VÌ SAO KHÔNG ĐỔI THẲNG `app.timezone` THÀNH Asia/Ho_Chi_Minh.
 *
 * Cột DATETIME của MySQL không mang múi giờ: nó chỉ là một chuỗi số.
 * Toàn bộ dữ liệu cũ đã được ghi theo UTC. Đổi cấu hình thì cùng chuỗi đó
 * được đọc thành giờ Hà Nội — mọi mốc trong quá khứ lệch 7 tiếng, vĩnh
 * viễn, và không có cách nào phân biệt dòng nào đã ghi theo cách nào.
 *
 * Nên: LƯU vẫn UTC, chỉ ĐỔI Ở HAI ĐẦU — lúc in ra cho người đọc, và lúc
 * nhận giờ người gõ vào. Cả hai đầu đều đi qua đây.
 *
 * ============================================================
 * HAI CHIỀU, KHÔNG PHẢI MỘT.
 *
 * Chỉ sửa chiều hiển thị là làm hỏng thêm: ô `datetime-local` gửi lên
 * giờ người gõ ("08:00" là 8 giờ sáng Hà Nội), mà chuỗi đó đang được cất
 * thẳng vào cột như thể nó là UTC. Khuyến mại hẹn 8h sáng vì thế **chạy
 * lúc 15h**. Sửa một đầu mà quên đầu kia thì con số hiển thị đúng lên
 * trong khi giờ kích hoạt sai đi.
 */
final class Gio
{
    /** Múi giờ người dùng đọc và gõ. */
    public static function mui(): string
    {
        return (string) config('app.display_timezone', 'Asia/Ho_Chi_Minh');
    }

    /** Múi giờ dữ liệu được lưu. */
    public static function muiLuu(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    /**
     * ĐỌC RA: một mốc đã lưu, đổi sang giờ người đọc.
     *
     * Nhận null và trả null: `null` nghĩa là CHƯA CÓ MỐC NÀO (chưa giao,
     * chưa hết hạn, chưa đăng). Biến nó thành "bây giờ" là bịa ra một sự
     * kiện chưa xảy ra.
     */
    public static function hien(DateTimeInterface|string|null $moc): ?Carbon
    {
        if ($moc === null || $moc === '') {
            return null;
        }

        return Carbon::parse($moc)->setTimezone(self::mui());
    }

    /**
     * GHI VÀO: chuỗi giờ người gõ ở ô `datetime-local`, đổi về giờ lưu.
     *
     * Trình duyệt gửi lên dạng `2026-09-10T08:00` — KHÔNG kèm múi giờ,
     * vì đó là giờ trên đồng hồ của người đang gõ. Phải nói rõ nó thuộc
     * múi nào trước khi đổi; `Carbon::parse()` trần sẽ hiểu là giờ lưu và
     * giữ nguyên con số, đúng cái lỗi đang sửa.
     */
    public static function nhan(DateTimeInterface|string|null $oNhap): ?Carbon
    {
        if ($oNhap === null || $oNhap === '') {
            return null;
        }

        if ($oNhap instanceof DateTimeInterface) {
            return Carbon::instance($oNhap)->setTimezone(self::muiLuu());
        }

        return Carbon::parse($oNhap, self::mui())->setTimezone(self::muiLuu());
    }

    /**
     * Giá trị điền sẵn cho ô `datetime-local`.
     *
     * Ô này chỉ nhận đúng dạng `Y-m-d\TH:i`; đưa dạng khác vào thì trình
     * duyệt bỏ qua trong im lặng và ô hiện ra trống — người sửa tưởng
     * mốc cũ đã bị xoá.
     */
    public static function choO(DateTimeInterface|string|null $moc): ?string
    {
        return self::hien($moc)?->format('Y-m-d\TH:i');
    }

    /**
     * Đổi các ô giờ người gõ trong MỘT biểu mẫu về giờ lưu.
     *
     * Gọi trong `prepareForValidation()`, tức là TRƯỚC khi kiểm tra — để
     * luật `after_or_equal:starts_at` so hai mốc đã cùng múi, và để
     * `validated()` trả ra đúng thứ sẽ cất vào cơ sở dữ liệu.
     *
     * Chỉ đụng vào ô CÓ GỬI LÊN và KHÔNG RỖNG. Ô trống nghĩa là "không
     * đặt mốc" — biến nó thành một mốc nào đó là tự ý đặt hạn cho khuyến
     * mại mà người dùng cố ý để mở.
     *
     * Trả chuỗi `Y-m-d H:i:s` chứ không trả đối tượng: luật `date` của
     * Laravel và Eloquent đều nhận chuỗi, còn đối tượng Carbon đi qua
     * `merge()` rồi `validated()` thì mỗi tầng hiểu một kiểu.
     *
     * @param  array<string, mixed>  $duLieu  dữ liệu thô của biểu mẫu
     * @return array<string, string>  chỉ những ô cần ghi đè
     */
    public static function doiONhap(array $duLieu, string ...$khoa): array
    {
        $ra = [];

        foreach ($khoa as $k) {
            $gt = $duLieu[$k] ?? null;

            if (! is_string($gt) || trim($gt) === '') {
                continue;
            }

            $moc = self::nhan(trim($gt));

            if ($moc !== null) {
                $ra[$k] = $moc->format('Y-m-d H:i:s');
            }
        }

        return $ra;
    }

    /**
     * Giá trị điền sẵn cho ô `date` (chỉ ngày).
     */
    public static function choONgay(DateTimeInterface|string|null $moc): ?string
    {
        return self::hien($moc)?->format('Y-m-d');
    }
}
