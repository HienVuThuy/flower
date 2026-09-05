<?php

namespace App\Enums;

/**
 * NGŨ HÀNH — mệnh mà một cây được coi là hợp.
 * ============================================================
 * ĐÂY LÀ TẬP QUÁN VĂN HOÁ, KHÔNG PHẢI SỰ THẬT KHOA HỌC, và giao diện
 * phải nói đúng như vậy. Cửa hàng bán cây cảnh ở Việt Nam thì rất nhiều
 * khách hỏi "cây này hợp mệnh gì" — bỏ qua là bỏ mất một nhu cầu có
 * thật. Nhưng trình bày nó như một chỉ số kỹ thuật thì thành ra khẳng
 * định thay khách một điều mà cửa hàng không có tư cách khẳng định.
 *
 * Cách xử lý: gọi đúng tên là "theo quan niệm phong thuỷ", và ADMIN TỰ
 * GÁN cho từng sản phẩm — hệ thống KHÔNG tự suy ra mệnh từ tên hay màu
 * cây. Tự suy là bịa dữ liệu.
 *
 * Một cây gán được NHIỀU mệnh: quan niệm dân gian vốn không loại trừ
 * nhau (cây lá xanh thường được coi là hợp cả Mộc lẫn Hoả theo lẽ tương
 * sinh). Vì thế đây là quan hệ nhiều-nhiều, không phải một cột trên
 * bảng products.
 */
enum FengShuiElement: string
{
    case Kim = 'kim';
    case Moc = 'moc';
    case Thuy = 'thuy';
    case Hoa = 'hoa';
    case Tho = 'tho';

    public function label(): string
    {
        return match ($this) {
            self::Kim => 'Mệnh Kim',
            self::Moc => 'Mệnh Mộc',
            self::Thuy => 'Mệnh Thuỷ',
            self::Hoa => 'Mệnh Hoả',
            self::Tho => 'Mệnh Thổ',
        };
    }

    /** Màu thường gắn với hành này — dùng để giải thích cho khách. */
    public function colorHint(): string
    {
        return match ($this) {
            self::Kim => 'trắng, xám, ánh kim',
            self::Moc => 'xanh lá, xanh lục',
            self::Thuy => 'xanh dương, đen',
            self::Hoa => 'đỏ, hồng, cam, tím',
            self::Tho => 'vàng, nâu đất',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
