<?php

namespace App\Enums;

/**
 * MÀU CHỦ ĐẠO của sản phẩm.
 * ============================================================
 * Đây là tiêu chí lọc PHỔ BIẾN NHẤT ở một cửa hàng hoa, và cũng là tiêu
 * chí duy nhất trong nhóm này mà khách gõ thẳng vào ô tìm kiếm: "hoa hồng
 * trắng", "hoa vàng tặng mẹ".
 *
 * MỘT SẢN PHẨM CÓ THỂ CÓ NHIỀU MÀU, nên nó là nhãn (product_traits) chứ
 * không phải một cột. Bó hoa mix ba màu thì gắn cả ba — khách lọc màu
 * nào cũng thấy nó, đúng như họ mong đợi.
 *
 * `hex()` LÀ ĐỂ VẼ CHẤM MÀU, KHÔNG PHẢI ĐỂ TẢ ĐÚNG SẢN PHẨM. Một bó hồng
 * đỏ có hàng chục sắc đỏ khác nhau; chấm màu chỉ giúp mắt quét nhanh
 * danh sách bộ lọc. Ảnh sản phẩm mới là thứ nói thật về màu.
 */
enum PlantColor: string
{
    case Red = 'red';
    case Pink = 'pink';
    case Orange = 'orange';
    case Yellow = 'yellow';
    case White = 'white';
    case Purple = 'purple';
    case Blue = 'blue';
    case Green = 'green';
    case Brown = 'brown';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Red => 'Đỏ',
            self::Pink => 'Hồng',
            self::Orange => 'Cam',
            self::Yellow => 'Vàng',
            self::White => 'Trắng',
            self::Purple => 'Tím',
            self::Blue => 'Xanh dương',
            self::Green => 'Xanh lá',
            self::Brown => 'Nâu',
            self::Mixed => 'Nhiều màu',
        };
    }

    /**
     * Mã màu để vẽ chấm tròn cạnh nhãn.
     *
     * `Mixed` không có một màu nào đại diện được, nên trả về null và
     * giao diện vẽ chấm nhiều màu thay vì chọn bừa một màu trong đó.
     */
    public function hex(): ?string
    {
        return match ($this) {
            self::Red => '#c62828',
            self::Pink => '#ec8ba7',
            self::Orange => '#ef7b2b',
            self::Yellow => '#f2c229',
            self::White => '#f6f4ef',
            self::Purple => '#8e6bb5',
            self::Blue => '#4a7fb5',
            self::Green => '#4c8b5b',
            // Sen đá nâu, thân bonsai, hoa khô — nâu là màu có thật của
            // hàng, không phải màu thiếu.
            self::Brown => '#8a6a4b',
            self::Mixed => null,
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
