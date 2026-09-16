<?php

namespace App\Services\Time;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/** Một nơi duy nhất biết "giờ người Việt đọc" khác "giờ đã lưu". */
final class Gio
{
    public static function mui(): string
    {
        return (string) config('app.display_timezone', 'Asia/Ho_Chi_Minh');
    }

    public static function muiLuu(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    public static function hien(DateTimeInterface|string|null $moc): ?Carbon
    {
        if ($moc === null || $moc === '') {
            return null;
        }

        return Carbon::parse($moc)->setTimezone(self::mui());
    }

    public static function nhan(DateTimeInterface|string|null $oNhap): ?Carbon
    {
        if ($oNhap === null || $oNhap === '') {
            return null;
        }

        if ($oNhap instanceof DateTimeInterface) {
            return Carbon::instance($oNhap)->setTimezone(self::muiLuu());
        }

        return Carbon::parse($oNhap, self::mui())->setTimezone(self::muiLuu());
    }

    public static function choO(DateTimeInterface|string|null $moc): ?string
    {
        return self::hien($moc)?->format('Y-m-d\TH:i');
    }

    public static function doiONhap(array $duLieu, string ...$khoa): array
    {
        $ra = [];

        foreach ($khoa as $k) {
            $gt = $duLieu[$k] ?? null;

            if (! is_string($gt) || trim($gt) === '') {
                continue;
            }

            $moc = self::nhan(trim($gt));

            if ($moc !== null) {
                $ra[$k] = $moc->format('Y-m-d H:i:s');
            }
        }

        return $ra;
    }

    public static function choONgay(DateTimeInterface|string|null $moc): ?string
    {
        return self::hien($moc)?->format('Y-m-d');
    }
}
