<?php

namespace App\Enums;

/**
 * Giao diện của một quyển sổ — màu giấy, màu nhấn, hoa văn.
 * ============================================================
 * BỘ CHỌN SẴN, KHÔNG PHẢI Ô CHỌN MÀU TỰ DO.
 *
 * Cho người dùng tự chọn mã màu nghe có vẻ tự do hơn, nhưng kết quả là
 * những quyển sổ chữ xám nhạt trên nền xám nhạt — và không có gì trong
 * hệ thống ngăn được, vì màu nào cũng "hợp lệ".
 *
 * Sáu bộ dưới đây đều đã ĐO tương phản màu nhấn trên màu giấy, ở CẢ nền
 * sáng lẫn nền tối. Người dùng vẫn thấy sổ mình khác sổ người khác, mà
 * không quyển nào không đọc nổi.
 *
 *   bộ            nền sáng   nền tối
 *   ------------  ---------  ---------
 *   Giấy trắng      6,05       4,61
 *   Lá non          8,18       7,34
 *   Gốm đỏ          5,63       7,20
 *   Chiều tím       7,90       7,18
 *   Cát ấm          5,87       8,77
 *   Rêu đá          7,78       7,52
 *
 * Cả mười hai tổ hợp đều vượt 4,5:1 — ngưỡng của CHỮ THƯỜNG, cao hơn hẳn
 * ngưỡng 3:1 mà nét đồ hoạ (viền ô tích, thanh tiến độ, nhãn dán) cần.
 * Thêm bộ mới thì phải đo lại và ghi số vào bảng này; con số đo được là
 * thứ duy nhất chứng minh được, còn "trông thì ổn" thì không.
 *
 * ============================================================
 * MÀU ĐỊNH NGHĨA BẰNG BIẾN CSS, KHÔNG PHẢI MÃ MÀU CỨNG.
 *
 * `token()` trả về tên lớp CSS; toàn bộ mã màu nằm ở
 * `resources/css/components/journal.css`, nơi đã có sẵn cơ chế đổi theo
 * nền sáng/tối của cả trang. Nhét mã màu vào PHP thì bộ nền tối bỏ sót
 * đúng phần này, và nó chỉ lộ ra khi có người mở sổ lúc trời tối.
 */
enum JournalTheme: string
{
    case Paper = 'paper';
    case Leaf = 'leaf';
    case Terracotta = 'terracotta';
    case Dusk = 'dusk';
    case Sand = 'sand';
    case Moss = 'moss';

    public function label(): string
    {
        return match ($this) {
            self::Paper => 'Giấy trắng',
            self::Leaf => 'Lá non',
            self::Terracotta => 'Gốm đỏ',
            self::Dusk => 'Chiều tím',
            self::Sand => 'Cát ấm',
            self::Moss => 'Rêu đá',
        };
    }

    /** Lớp CSS gắn vào khung sổ. Mã màu nằm ở journal.css. */
    public function token(): string
    {
        return 'journal-theme--' . $this->value;
    }

    /**
     * Hoa văn nền — khoá của một `<pattern>` trong bộ SVG.
     *
     * `null` = giấy trơn. Không phải bộ nào cũng cần hoa văn: một quyển
     * sổ dùng để ghi giá thì hoa văn lá chỉ làm rối chỗ đọc số.
     */
    public function pattern(): ?string
    {
        return match ($this) {
            self::Paper => null,
            self::Leaf => 'leaves',
            self::Terracotta => 'arches',
            self::Dusk => 'dots',
            self::Sand => 'grain',
            self::Moss => 'pebbles',
        };
    }

    public static function default(): self
    {
        return self::Paper;
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
