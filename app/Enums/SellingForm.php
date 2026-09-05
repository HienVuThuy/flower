<?php

namespace App\Enums;

/**
 * Hình thức bán của sản phẩm hoa - cây cảnh.
 * ============================================================
 * Guide mục 4.3: "Các hình thức này không nên chỉ được xử lý bằng một
 * chuỗi text tùy ý nếu có cách thiết kế tốt hơn", và phải cho phép lọc,
 * thống kê, đề xuất, phân tích hành vi.
 *
 * Trước đây danh sách này được khai ở BA nơi: hằng SELLING_FORMS trong
 * Shop\ProductController, và Rule::in trong hai FormRequest. Ba bản
 * hiện đang khớp nhau nhưng không có gì bảo đảm điều đó — thêm một hình
 * thức mới mà quên sửa một chỗ là admin lưu được còn cửa hàng không
 * hiển thị đúng tên.
 *
 * QUYẾT ĐỊNH NGHIỆP VỤ: "Cây để bàn" và "Bonsai" là CATEGORY, không
 * phải hình thức bán — xem docs/DOMAIN-DECISIONS.md (QĐ-01). Guide §4.3
 * liệt kê chúng ở đây nhưng §24 lại xếp vào Category; đã chốt theo §24.
 * Đừng thêm 'desk_plant'/'bonsai' vào enum này.
 *
 * Cột products.selling_form ĐÃ được cast sang enum này (xem $casts trong
 * App\Models\Product). Nghĩa là $product->selling_form trả về một case
 * của enum, không phải chuỗi — dùng ->label() để lấy nhãn và ->value khi
 * cần so sánh với dữ liệu từ form hay URL.
 */
enum SellingForm: string
{
    case Bouquet = 'bouquet';
    case Pot = 'pot';
    case Branch = 'branch';
    case Basket = 'basket';
    case Box = 'box';
    case Arrangement = 'arrangement';
    case Original = 'original';
    case Set = 'set';
    case Gift = 'gift';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Bouquet => 'Bó hoa',
            self::Pot => 'Cây chậu',
            self::Branch => 'Cành',
            self::Basket => 'Giỏ hoa',
            self::Box => 'Hộp hoa',
            self::Arrangement => 'Lẵng / kệ hoa',
            self::Original => 'Cây nguyên bản',
            self::Set => 'Set',
            self::Gift => 'Quà tặng',
            self::Other => 'Khác',
        };
    }

    /**
     * Hình thức này cần bộ thông tin chăm sóc nào.
     *
     * Cây còn sống trong chậu/nguyên bản thì chăm sóc lâu dài; hoa đã
     * cắt (bó, cành, giỏ, hộp, lẵng) thì chỉ cần giữ tươi vài ngày.
     */
    public function careProfile(): CareProfile
    {
        return match ($this) {
            self::Pot, self::Original => CareProfile::LivingPlant,
            self::Bouquet, self::Branch, self::Basket, self::Box, self::Arrangement => CareProfile::CutFlower,
            self::Set, self::Gift, self::Other => CareProfile::Minimal,
        };
    }

    /** @return array<string, string> giá trị => nhãn, dùng cho <select> và bộ lọc */
    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }

    /** @return array<int, string> danh sách giá trị hợp lệ cho Rule::in */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
