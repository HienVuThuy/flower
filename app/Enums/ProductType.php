<?php

namespace App\Enums;

/**
 * Sản phẩm này VỀ BẢN CHẤT là cái gì.
 * ============================================================
 * Một trong ba trục đã chốt ở QĐ-01 (docs/DOMAIN-DECISIONS.md):
 *
 *   category     — khách đang tìm loại sản phẩm nào (gồm cả DỊP:
 *                  "Hoa cưới", "Hoa quà tặng")
 *   product_type — món hàng này về bản chất là gì   ← enum này
 *   selling_form — bán dưới dạng/quy cách nào
 *
 * VÌ SAO KHÔNG CÒN 'event' / 'wedding' / 'gift':
 * Ba giá trị đó trả lời câu hỏi "mua để làm gì", không phải "đây là
 * cái gì" — tức là trục DỊP, mà trục dịp đã có nhà riêng là Category.
 * Để chung một cột khiến trang sản phẩm in ra hai dòng giống hệt nhau:
 *
 *     Danh mục      : Hoa cưới
 *     Loại sản phẩm : Hoa cưới
 *
 * Bó hoa cầm tay cô dâu VẪN LÀ hoa; việc nó dành cho đám cưới nằm ở
 * category "Hoa cưới". Xem QĐ-08 để biết cách chuyển dữ liệu cũ.
 *
 * VÌ SAO CHỈ CÓ BA GIÁ TRỊ:
 * Đây đúng là những gì cửa hàng đang bán. Không thêm 'accessory',
 * 'seed', 'tool'… cho "đủ bộ" khi chưa có sản phẩm nào như vậy —
 * thêm lựa chọn rỗng chỉ làm người nhập liệu phân vân.
 */
enum ProductType: string
{
    case Flower = 'flower';
    case Plant = 'plant';
    case Other = 'other';

    /**
     * Những hình thức bán hợp lệ cho loại hàng này.
     *
     * VÌ SAO CẦN: hai cột product_type và selling_form đang được kiểm
     * RIÊNG RẼ — mỗi cột chỉ hỏi "giá trị này có trong danh sách
     * không". Nên lưu được một "cây cảnh" bán dưới hình thức "bó hoa".
     *
     * Không phải chuyện thẩm mỹ. selling_form quyết định BỘ THÔNG TIN
     * CHĂM SÓC của sản phẩm (xem SellingForm::careProfile()), và trang
     * tư vấn chọn cây lọc hàng theo đúng cột này. Một cây chậu gắn nhãn
     * "bó hoa" sẽ mất các ô ánh sáng/đất/tưới nước, rồi biến mất khỏi
     * mọi gợi ý cây cảnh — sai lặng lẽ, không có thông báo nào.
     *
     * DANH SÁCH NÀY ĐỐI CHIẾU VỚI DỮ LIỆU ĐANG CHẠY, không phải nghĩ ra
     * cho gọn: mọi tổ hợp hiện có trong cơ sở dữ liệu đều nằm trong đây.
     * Thêm Gift và Other để không chặn nhầm hàng bán thật sau này.
     *
     * @return array<int, SellingForm>
     */
    public function allowedSellingForms(): array
    {
        return match ($this) {
            // Hoa tươi: bán theo bó, giỏ, hộp, lẵng, cành.
            self::Flower => [
                SellingForm::Bouquet,
                SellingForm::Basket,
                SellingForm::Box,
                SellingForm::Arrangement,
                SellingForm::Branch,
                SellingForm::Gift,
                SellingForm::Other,
            ],

            // Cây cảnh: bán theo chậu, cây trần, hoặc bộ.
            self::Plant => [
                SellingForm::Pot,
                SellingForm::Original,
                SellingForm::Set,
                SellingForm::Gift,
                SellingForm::Other,
            ],

            // Vật tư, phụ kiện: bán lẻ hoặc theo bộ.
            self::Other => [
                SellingForm::Set,
                SellingForm::Gift,
                SellingForm::Other,
            ],
        };
    }

    /**
     * Loại hàng này có hợp với nhóm danh mục kia không.
     *
     * Danh mục "vật tư" và danh mục "cây/hoa" là HAI GIAN HÀNG KHÁC
     * NHAU trên trang khách — Product::scopeMainCatalog() và
     * scopeSupplyCatalog() chia hàng theo đúng cột kind của danh mục.
     * Xếp một bó hoa vào danh mục vật tư thì nó biến mất khỏi gian hoa
     * và hiện lên giữa đám đất trồng với bình xịt.
     *
     * Danh mục chưa khai kind (kind = null) là dữ liệu cũ, coi như
     * cây/hoa — đúng như cách scopePlants() đang hiểu.
     */
    public function fitsCategoryKind(?CategoryKind $kind): bool
    {
        return match ($kind ?? CategoryKind::Plant) {
            CategoryKind::Supply => $this === self::Other,
            CategoryKind::Plant => $this !== self::Other,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Flower => 'Hoa',
            self::Plant => 'Cây cảnh',
            self::Other => 'Khác',
        };
    }

    /** @return array<string, string> giá trị => nhãn, dùng cho <select> */
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
