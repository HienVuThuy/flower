<?php

namespace App\Enums;

/** VỊ TRÍ ĐẶT phù hợp của một cây. */
enum Placement: string
{
    case Balcony = 'balcony';
    case WindowSill = 'window_sill';
    case LivingRoom = 'living_room';
    case Bedroom = 'bedroom';
    case Kitchen = 'kitchen';
    case Bathroom = 'bathroom';
    case Hallway = 'hallway';
    case Office = 'office';
    case Desk = 'desk';
    case Shop = 'shop';
    case Garden = 'garden';

    public function label(): string
    {
        return match ($this) {
            self::Balcony => 'Ban công',
            self::WindowSill => 'Bệ cửa sổ',
            self::LivingRoom => 'Phòng khách',
            self::Bedroom => 'Phòng ngủ',
            self::Kitchen => 'Phòng bếp',
            self::Bathroom => 'Phòng tắm',
            self::Hallway => 'Hành lang, cầu thang',
            self::Office => 'Văn phòng',
            self::Desk => 'Bàn làm việc',
            self::Shop => 'Quán, cửa hàng',
            self::Garden => 'Sân vườn',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Balcony => 'Nắng trực tiếp, gió nhiều',
            self::WindowSill => 'Nắng gián tiếp cả ngày, chỗ hẹp',
            self::LivingRoom => 'Sáng gián tiếp, rộng rãi',
            self::Bedroom => 'Ít nắng, cần cây không mùi mạnh',
            self::Kitchen => 'Ẩm, nhiều hơi dầu mỡ, nhiệt độ đổi liên tục',
            self::Bathroom => 'Ẩm cao, rất ít nắng',
            self::Hallway => 'Tối, ít người để ý tưới',
            self::Office => 'Đèn huỳnh quang, điều hoà khô',
            self::Desk => 'Chỗ hẹp, cần cây nhỏ',
            self::Shop => 'Đèn cả ngày, nhiều người qua lại',
            self::Garden => 'Ngoài trời, nắng mưa trực tiếp',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Balcony, self::Garden, self::WindowSill => 'brightness-high',
            self::Bathroom, self::Kitchen => 'droplet',
            self::Office, self::Desk => 'speedometer2',
            self::Shop => 'bag',
            self::Hallway => 'list',
            default => 'flower2',
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
