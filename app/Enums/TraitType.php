<?php

namespace App\Enums;

/**
 * Các loại nhãn phân loại nhiều-giá-trị của sản phẩm.
 * ============================================================
 * MỘT BẢNG CHO NHIỀU LOẠI NHÃN — vì sao không tách mỗi loại một bảng:
 *
 * `placement`, `feng_shui` và `accessory_for` có CẤU TRÚC HOÀN TOÀN GIỐNG
 * NHAU (một sản phẩm, một chuỗi giá trị, quan hệ nhiều-nhiều) và được
 * dùng theo đúng một kiểu truy vấn ("sản phẩm nào mang nhãn X"). Ba bảng
 * riêng nghĩa là ba migration, ba model, ba đoạn mã lưu trong form admin
 * — tất cả chỉ khác nhau ở tên bảng.
 *
 * ĐIỀU KIỆN ĐỂ CÁCH NÀY KHÔNG BIẾN THÀNH BẢNG RÁC: giá trị của mỗi loại
 * phải nằm trong một enum đóng, và ProductTrait::isValid() kiểm tra
 * trước khi ghi. Không có ràng buộc đó thì đây thành cái thùng chứa mọi
 * thứ, và không ai còn biết trong bảng có những gì.
 *
 * KHÔNG dùng cho những thứ đã có chỗ riêng: danh mục, hình thức bán, độ
 * khó chăm sóc đều là MỘT giá trị mỗi sản phẩm và đã có cột riêng. Nhét
 * vào đây là làm mất ràng buộc mà cột đang giữ.
 */
enum TraitType: string
{
    /** Vị trí đặt phù hợp — xem App\Enums\Placement. */
    case Placement = 'placement';

    /** Mệnh hợp theo quan niệm phong thuỷ — xem App\Enums\FengShuiElement. */
    case FengShui = 'feng_shui';

    /**
     * Phụ kiện này dùng kèm loại hàng nào.
     *
     * Giá trị là `value` của App\Enums\SellingForm ('pot', 'bouquet'...),
     * hoặc 'all' cho phụ kiện dùng chung. Nhờ vậy "mua kèm" tra được mà
     * không cần bảng ghép sản phẩm-với-sản phẩm — thứ mà admin sẽ phải
     * ngồi nối tay từng cặp một.
     */
    case AccessoryFor = 'accessory_for';

    /**
     * Môi trường sống tự nhiên — xem App\Enums\Habitat.
     *
     * KHÔNG TRÙNG với Placement: Placement là "khách đặt cây ở đâu trong
     * nhà", Habitat là "ngoài thiên nhiên cây này vốn mọc ở đâu". Cùng
     * một cây có thể đặt ở phòng khách nhưng gốc gác là sa mạc, và đó
     * mới là thứ quyết định cách tưới.
     */
    case Habitat = 'habitat';

    /** Dạng sống: thân gỗ, thân leo, thân thảo... — xem App\Enums\GrowthForm. */
    case GrowthForm = 'growth_form';

    /** Dáng cây/bó hoa — xem App\Enums\PlantShape. */
    case Shape = 'shape';

    /** Màu chủ đạo — xem App\Enums\PlantColor. */
    case Color = 'color';

    public function label(): string
    {
        return match ($this) {
            self::Placement => 'Vị trí đặt',
            self::FengShui => 'Hợp mệnh',
            self::AccessoryFor => 'Dùng kèm',
            self::Habitat => 'Môi trường sống',
            self::GrowthForm => 'Dạng sống',
            self::Shape => 'Dáng',
            self::Color => 'Màu sắc',
        };
    }

    /**
     * Những nhãn khách LỌC ĐƯỢC ở trang sản phẩm.
     *
     * KHÔNG gồm `AccessoryFor`: đó là nhãn nội bộ để tra phụ kiện mua
     * kèm, không phải một tiêu chí khách chọn. Hiện nó ra thành bộ lọc
     * "Dùng kèm: chậu / bó / giỏ" là bày cho khách một câu hỏi mà họ
     * không có lý do gì để trả lời.
     *
     * @return list<self>
     */
    public static function filterable(): array
    {
        return [
            self::Color,
            self::GrowthForm,
            self::Habitat,
            self::Shape,
            self::Placement,
            self::FengShui,
        ];
    }

    /**
     * Tên tham số trên URL — tiếng Việt không dấu, như các trang khác.
     *
     * Đặt Ở ĐÂY chứ không rải trong controller và view: tên tham số phải
     * giống hệt nhau ở ba chỗ (dựng link, đọc request, đánh dấu chip
     * đang chọn), và ba bản chép tay sẽ lệch nhau.
     */
    public function queryKey(): string
    {
        return match ($this) {
            self::Placement => 'vi-tri',
            self::FengShui => 'menh',
            self::AccessoryFor => 'dung-kem',
            self::Habitat => 'moi-truong',
            self::GrowthForm => 'dang-song',
            self::Shape => 'dang',
            self::Color => 'mau',
        };
    }

    /** Tra ngược từ tham số URL về loại nhãn. */
    public static function fromQueryKey(string $key): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->queryKey() === $key) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Các giá trị hợp lệ của loại nhãn này.
     *
     * @return array<string, string> value => label
     */
    public function options(): array
    {
        return match ($this) {
            self::Placement => Placement::options(),
            self::FengShui => FengShuiElement::options(),
            self::AccessoryFor => ['all' => 'Mọi loại hàng'] + SellingForm::options(),
            self::Habitat => Habitat::options(),
            self::GrowthForm => GrowthForm::options(),
            self::Shape => PlantShape::options(),
            self::Color => PlantColor::options(),
        };
    }

    /** Nhãn tiếng Việt của một giá trị, hoặc chính giá trị nếu không tra được. */
    public function labelFor(string $value): string
    {
        return $this->options()[$value] ?? $value;
    }
}
