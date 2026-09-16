<?php

namespace App\Enums;

/**
 * Cảm xúc dưới một bài Góc cây.
 * ============================================================
 * NĂM LOẠI, KHÔNG BẢY như Facebook: "phẫn nộ" và "thương thương" ở một trang
 * bán cây gần như không có chỗ dùng, mà mỗi loại thêm vào là một ô nữa trong
 * bảng chọn trên màn hình điện thoại.
 *
 * Biểu tượng là SVG (bộ icon của trang), KHÔNG phải emoji: emoji là chữ khách
 * gõ, còn đây là nút bấm của giao diện — mỗi máy hiển thị emoji một kiểu.
 */
enum CommunityReaction: string
{
    case Thich = 'thich';
    case Yeu = 'yeu';
    case Haha = 'haha';
    case Wow = 'wow';
    case Buon = 'buon';

    public function label(): string
    {
        return match ($this) {
            self::Thich => 'Thích',
            self::Yeu => 'Yêu thích',
            self::Haha => 'Haha',
            self::Wow => 'Wow',
            self::Buon => 'Buồn',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Thich => 'hand-thumbs-up-fill',
            self::Yeu => 'heart-fill',
            self::Haha => 'emoji-laughing-fill',
            self::Wow => 'emoji-surprise-fill',
            self::Buon => 'emoji-frown-fill',
        };
    }

    /** Lớp CSS cho màu của từng cảm xúc. */
    public function mau(): string
    {
        return 'cam-xuc--' . $this->value;
    }

    public static function macDinh(): self
    {
        return self::Thich;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
