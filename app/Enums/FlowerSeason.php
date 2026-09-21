<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/** MÙA HOA — chỉ gắn cho hoa có mùa rõ; hoa có quanh năm để trống. */
enum FlowerSeason: string
{
    case Xuan = 'xuan';
    case Ha = 'ha';
    case Thu = 'thu';
    case Dong = 'dong';

    public function label(): string
    {
        return match ($this) {
            self::Xuan => 'Mùa xuân',
            self::Ha => 'Mùa hạ',
            self::Thu => 'Mùa thu',
            self::Dong => 'Mùa đông',
        };
    }

    public function thang(): string
    {
        return match ($this) {
            self::Xuan => 'tháng 2 – 4',
            self::Ha => 'tháng 5 – 7',
            self::Thu => 'tháng 8 – 10',
            self::Dong => 'tháng 11 – 1',
        };
    }

    public static function cua(CarbonInterface $ngay): self
    {
        return match (true) {
            in_array($ngay->month, [2, 3, 4], true) => self::Xuan,
            in_array($ngay->month, [5, 6, 7], true) => self::Ha,
            in_array($ngay->month, [8, 9, 10], true) => self::Thu,
            default => self::Dong,
        };
    }

    public function ke(): self
    {
        return match ($this) {
            self::Xuan => self::Ha,
            self::Ha => self::Thu,
            self::Thu => self::Dong,
            self::Dong => self::Xuan,
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
