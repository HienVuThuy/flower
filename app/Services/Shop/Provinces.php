<?php

namespace App\Services\Shop;

/** Danh sách tỉnh/thành để chọn và để kiểm tra hợp lệ. */
class Provinces
{
    public static function all(): array
    {
        return array_merge(
            config('provinces.cities', []),
            config('provinces.provinces', []),
        );
    }

    public static function grouped(): array
    {
        return [
            'Thành phố trực thuộc trung ương' => config('provinces.cities', []),
            'Tỉnh' => config('provinces.provinces', []),
        ];
    }

    public static function isValid(?string $value): bool
    {
        return $value !== null && in_array($value, self::all(), true);
    }

    public static function shortName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return config('provinces.short_names')[$value] ?? $value;
    }
}
