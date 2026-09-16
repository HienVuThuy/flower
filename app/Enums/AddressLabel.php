<?php

namespace App\Enums;

/** Loại địa chỉ — chỉ để khách nhận ra nhanh trong danh sách của mình. */
enum AddressLabel: string
{
    case Home = 'home';
    case Office = 'office';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Nhà riêng',
            self::Office => 'Văn phòng',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
