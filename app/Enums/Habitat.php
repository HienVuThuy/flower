<?php

namespace App\Enums;

/** MÔI TRƯỜNG SỐNG TỰ NHIÊN của cây. */
enum Habitat: string
{
    case Terrestrial = 'terrestrial';
    case Underground = 'underground';
    case Aquatic = 'aquatic';
    case Semiaquatic = 'semiaquatic';
    case Desert = 'desert';
    case Lithophyte = 'lithophyte';
    case Epiphyte = 'epiphyte';
    case Rainforest = 'rainforest';
    case Temperate = 'temperate';

    public function label(): string
    {
        return match ($this) {
            self::Terrestrial => 'Trên mặt đất',
            self::Underground => 'Có phần sống dưới đất',
            self::Aquatic => 'Dưới nước',
            self::Semiaquatic => 'Nửa nước nửa cạn',
            self::Desert => 'Sa mạc, khô hạn',
            self::Lithophyte => 'Bám đá',
            self::Epiphyte => 'Bám thân cây khác',
            self::Rainforest => 'Rừng ẩm nhiệt đới',
            self::Temperate => 'Vùng ôn đới',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Terrestrial => 'Trồng chậu đất bình thường, tưới khi mặt đất se khô',
            self::Underground => 'Củ hoặc thân ngầm dưới đất — sợ úng hơn sợ hạn',
            self::Aquatic => 'Sống trong nước, không trồng đất',
            self::Semiaquatic => 'Chịu được gốc ngập, thích đất luôn ẩm',
            self::Desert => 'Rất chịu hạn, tưới thưa; úng nước là chết',
            self::Lithophyte => 'Bám đá trong tự nhiên, cần thoát nước thật nhanh',
            self::Epiphyte => 'Bám cây khác, rễ cần thoáng khí — không nén chặt đất',
            self::Rainforest => 'Ưa ẩm và sáng gián tiếp, sợ nắng gắt',
            self::Temperate => 'Quen khí hậu mát, cần chỗ thoáng khi trời nóng',
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
