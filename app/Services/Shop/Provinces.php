<?php

namespace App\Services\Shop;

/**
 * Danh sách tỉnh/thành để chọn và để kiểm tra hợp lệ.
 * ============================================================
 * MỘT NƠI DUY NHẤT đọc config/provinces.php. Ba chỗ cần danh sách này —
 * biểu mẫu thanh toán, sổ địa chỉ, và quy tắc kiểm tra của cả hai — nên
 * nếu mỗi chỗ tự gọi config() thì sớm muộn sẽ có chỗ quên nhóm "cities".
 */
class Provinces
{
    /**
     * Toàn bộ 34 đơn vị, phẳng, thành phố trước rồi tới tỉnh.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_merge(
            config('provinces.cities', []),
            config('provinces.provinces', []),
        );
    }

    /**
     * Chia nhóm cho thẻ <optgroup>.
     *
     * Hai nhóm này là phân loại hành chính CÓ THẬT (thành phố trực thuộc
     * trung ương / tỉnh), không phải nhóm tự nghĩ ra cho đẹp.
     *
     * @return array<string, array<int, string>>
     */
    public static function grouped(): array
    {
        return [
            'Thành phố trực thuộc trung ương' => config('provinces.cities', []),
            'Tỉnh' => config('provinces.provinces', []),
        ];
    }

    /**
     * Giá trị này có nằm trong danh sách hiện hành không.
     *
     * DÙNG CHO DỮ LIỆU MỚI NHẬP, KHÔNG DÙNG ĐỂ ĐỌC DỮ LIỆU CŨ.
     * Đơn hàng lưu bản chụp tên tỉnh tại thời điểm đặt; sau một đợt sáp
     * nhập, tên cũ không còn trong danh sách nhưng vẫn phải hiển thị
     * đúng như đã ghi.
     */
    public static function isValid(?string $value): bool
    {
        return $value !== null && in_array($value, self::all(), true);
    }

    /**
     * Tên ngắn, dùng khi gọi API vận chuyển bên ngoài.
     *
     * "Thành phố Hà Nội" -> "Hà Nội". Tên không có trong bảng ánh xạ thì
     * trả về nguyên văn — 28 tỉnh vốn đã là tên ngắn, và tên cũ trên đơn
     * hàng lâu năm cũng vậy.
     *
     * KHÔNG DÙNG ĐỂ HIỂN THỊ hay để lưu vào cơ sở dữ liệu. Tên chính
     * thức là thứ phải in trên đơn giao hàng; hàm này chỉ để dịch sang
     * cách gọi của hệ thống bên thứ ba ngay tại điểm gọi API.
     */
    public static function shortName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return config('provinces.short_names')[$value] ?? $value;
    }
}
