<?php

namespace App\Services\Shop;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Request;

/** Chế độ hiển thị SÁNG / TỐI — lựa chọn của từng người xem. */
class DisplayScheme
{
    public const COOKIE = 'che_do_hien_thi';

    public const SANG = 'sang';

    public const TOI = 'toi';

    public const AUTO = 'auto';

    public static function choices(): array
    {
        return [self::SANG, self::TOI];
    }

    public static function all(): array
    {
        return [self::AUTO, self::SANG, self::TOI];
    }

    public static function label(string $scheme): string
    {
        return match ($scheme) {
            self::TOI => 'Nền tối',
            default => 'Nền sáng',
        };
    }

    public static function current(): string
    {
        $value = (string) Request::cookie(self::COOKIE, self::AUTO);

        return in_array($value, self::all(), true) ? $value : self::AUTO;
    }

    public static function cookie(string $scheme): \Symfony\Component\HttpFoundation\Cookie
    {
        $scheme = in_array($scheme, self::all(), true) ? $scheme : self::AUTO;

        return Cookie::make(
            name: self::COOKIE,
            value: $scheme,
            minutes: 60 * 24 * 365,
            httpOnly: false,
        );
    }
}
