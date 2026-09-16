<?php

namespace App\Enums;

/** DẠNG SỐNG của cây — thân gỗ, thân leo, thân thảo... */
enum GrowthForm: string
{
    case Tree = 'tree';
    case Shrub = 'shrub';
    case Herb = 'herb';
    case Vine = 'vine';
    case Succulent = 'succulent';
    case Cactus = 'cactus';
    case Fern = 'fern';
    case Palm = 'palm';
    case Bulb = 'bulb';
    case Grass = 'grass';

    public function label(): string
    {
        return match ($this) {
            self::Tree => 'Cây thân gỗ',
            self::Shrub => 'Cây bụi',
            self::Herb => 'Cây thân thảo',
            self::Vine => 'Cây thân leo',
            self::Succulent => 'Cây mọng nước',
            self::Cactus => 'Xương rồng',
            self::Fern => 'Dương xỉ',
            self::Palm => 'Họ cau dừa',
            self::Bulb => 'Cây thân củ',
            self::Grass => 'Cây thân cỏ',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Tree => 'Thân hoá gỗ, sống lâu năm và lớn dần theo thời gian',
            self::Shrub => 'Nhiều nhánh từ gốc, thấp hơn cây gỗ, dễ tạo dáng',
            self::Herb => 'Thân mềm, vòng đời ngắn, thay lá theo mùa',
            self::Vine => 'Cần cột, giàn hoặc chỗ treo để buông',
            self::Succulent => 'Lá dày trữ nước, chịu hạn tốt',
            self::Cactus => 'Gai thay lá, gần như không cần tưới thường xuyên',
            self::Fern => 'Sinh sản bằng bào tử, ưa ẩm và bóng râm',
            self::Palm => 'Thân cột không phân nhánh, tán lá to',
            self::Bulb => 'Trữ dinh dưỡng trong củ, nghỉ và mọc lại theo mùa',
            self::Grass => 'Thân đốt mảnh, mọc thành khóm',
        };
    }

    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
