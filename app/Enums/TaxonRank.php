<?php

namespace App\Enums;

/**
 * Bậc phân loại sinh học: Giới → Ngành → Lớp → Bộ → Họ → Chi → Loài.
 * ============================================================
 * BẢY BẬC CHÍNH, không lấy hết mọi bậc phụ (phân họ, tông, phân loài...).
 * Bảy bậc này là thứ dạy trong trường phổ thông và là thứ người mua cây
 * có thể đọc mà không cần tra cứu. Thêm bậc phụ vào là biến một trang
 * bán hàng thành một cơ sở dữ liệu thực vật học.
 *
 * `level()` TRẢ VỀ SỐ, và đó là điểm quan trọng: thứ tự các bậc phải so
 * sánh được bằng phép toán, không bằng cách nhớ thứ tự trong enum. Nhờ
 * vậy mới kiểm được "cha phải cao bậc hơn con" ở một chỗ duy nhất.
 */
enum TaxonRank: string
{
    case Kingdom = 'kingdom';
    case Phylum = 'phylum';
    case ClassRank = 'class';
    case Order = 'order';
    case Family = 'family';
    case Genus = 'genus';
    case Species = 'species';

    public function label(): string
    {
        return match ($this) {
            self::Kingdom => 'Giới',
            self::Phylum => 'Ngành',
            self::ClassRank => 'Lớp',
            self::Order => 'Bộ',
            self::Family => 'Họ',
            self::Genus => 'Chi',
            self::Species => 'Loài',
        };
    }

    /**
     * Bậc này ở tầng thứ mấy — 1 là rộng nhất (Giới), 7 hẹp nhất (Loài).
     *
     * Dùng để kiểm ràng buộc "nút con phải hẹp hơn nút cha". Không có nó
     * thì dữ liệu có thể sinh ra một cái Họ nằm trong một cái Chi, và
     * cây phân loại mất hết ý nghĩa mà không có gì báo.
     */
    public function level(): int
    {
        return match ($this) {
            self::Kingdom => 1,
            self::Phylum => 2,
            self::ClassRank => 3,
            self::Order => 4,
            self::Family => 5,
            self::Genus => 6,
            self::Species => 7,
        };
    }

    /**
     * Tên khoa học của bậc này có được VIẾT NGHIÊNG không.
     *
     * Quy ước quốc tế: từ bậc Chi (Genus) trở xuống thì viết nghiêng
     * (*Monstera deliciosa*), còn Họ trở lên thì viết thẳng (Araceae).
     * Nhỏ nhặt, nhưng đây là thứ đầu tiên người có chuyên môn nhìn vào —
     * viết sai là mất tin cậy ngay ở dòng đầu tiên.
     */
    public function italic(): bool
    {
        return $this->level() >= self::Genus->level();
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
