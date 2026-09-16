<?php

namespace App\Enums;

/** Việc chăm sóc định kỳ có thể nhắc lịch được. */
enum CareTask: string
{
    case Water = 'water';
    case Fertilizer = 'fertilizer';

    public function configKey(): string
    {
        return match ($this) {
            self::Water => 'water_days',
            self::Fertilizer => 'fertilizer_days',
        };
    }

    public function adviceKey(): string
    {
        return match ($this) {
            self::Water => 'water',
            self::Fertilizer => 'fertilizer',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Water => 'Tưới nước',
            self::Fertilizer => 'Bón phân',
        };
    }

    public function headline(): string
    {
        return match ($this) {
            self::Water => 'Đến lúc tưới cây rồi',
            self::Fertilizer => 'Đến kỳ bón phân',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Water => 'droplet',
            self::Fertilizer => 'moisture',
        };
    }
}
