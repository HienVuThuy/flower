<?php

namespace App\Services\Analytics;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Gộp các cách viết khác nhau của cùng một tỉnh/thành. */
final class TenTinh
{
    private const TIEN_TO = ['thành phố ', 'tp. ', 'tp.', 'tp ', 'tỉnh '];

    public static function khoa(?string $ten): string
    {
        $k = Str::lower(trim((string) $ten));

        foreach (self::TIEN_TO as $tt) {
            if (str_starts_with($k, $tt)) {
                $k = trim(substr($k, strlen($tt)));
                break;
            }
        }

        return $k === '' ? '(không rõ)' : $k;
    }

    public static function nhan(Collection $cacTen): string
    {
        $pho = $cacTen->map(fn ($t) => trim((string) $t))->filter()->countBy()->sortDesc();

        return (string) ($pho->keys()->first() ?? '(không rõ tỉnh)');
    }
}
