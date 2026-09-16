<?php

namespace App\Services\Shop;

use App\Models\Setting;

/** NƠI DUY NHẤT quyết định một số tiền hiện ra trông như thế nào. */
class Money
{
    public const FIELDS = [
        'currency_code' => 'VND',
        'currency_symbol' => '₫',
        'currency_position' => 'after',
        'currency_decimals' => '0',
    ];

    public static function code(): string
    {
        return self::get('currency_code');
    }

    public static function symbol(): string
    {
        return self::get('currency_symbol');
    }

    public static function position(): string
    {
        return self::get('currency_position') === 'before' ? 'before' : 'after';
    }

    public static function decimals(): int
    {
        return max(0, min(4, (int) self::get('currency_decimals')));
    }

    public static function format(string|int|float|null $amount): string
    {
        $so = self::number($amount);
        $kyHieu = self::symbol();

        if ($kyHieu === '') {
            return $so;
        }

        return self::position() === 'before'
            ? $kyHieu . $so
            : $so . $kyHieu;
    }

    public static function number(string|int|float|null $amount): string
    {
        $le = self::decimals();

        return self::code() === 'USD'
            ? number_format((float) $amount, $le, '.', ',')
            : number_format((float) $amount, $le, ',', '.');
    }

    private static function get(string $key): string
    {
        if (array_key_exists($key, self::FIELDS)) {
            return self::FIELDS[$key];
        }

        $value = Setting::get($key, self::FIELDS[$key] ?? '');

        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : (self::FIELDS[$key] ?? '');
    }
}
