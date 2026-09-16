<?php

namespace App\Services\Shop;

use App\Models\Setting;

/** Thông tin nhận diện và thông tin thanh toán của cửa hàng. */
class StoreProfile
{
    public const FIELDS = [
        'site_name' => 'Angevil',

        'site_tagline' => 'Hoa tươi & Cây cảnh',

        'site_logo' => null,

        'site_hotline' => '0912345678',
        'site_email' => 'anaorin229@gmail.com',
        'site_address' => 'Trường Đại học Tài nguyên và Môi trường Hà Nội, '
            .'41A đường Phú Diễn, phường Phú Diễn, quận Bắc Từ Liêm, Hà Nội',

        'site_province' => 'Thành phố Hà Nội',
    ];

    public static function get(string $key): ?string
    {
        $value = Setting::get($key, self::FIELDS[$key] ?? null);

        return is_string($value) && trim($value) === ''
            ? (self::FIELDS[$key] ?? null)
            : $value;
    }

    public static function name(): string
    {
        return self::get('site_name') ?: (self::FIELDS['site_name'] ?? 'Cửa hàng');
    }

    public static function tagline(): ?string
    {
        return self::get('site_tagline');
    }

    public static function logoUrl(): ?string
    {
        $path = self::get('site_logo');

        return $path ? \Illuminate\Support\Facades\Storage::url($path) : null;
    }

    public static function hotline(): ?string
    {
        $so = trim((string) self::get('site_hotline'));

        return self::laSoDienThoai($so) ? $so : null;
    }

    public static function laSoDienThoai(string $chuoi): bool
    {
        if (! preg_match('/^\+?[\d\s.\-()]+$/', $chuoi)) {
            return false;
        }

        $chuSo = strlen(preg_replace('/\D/', '', $chuoi) ?? '');

        return $chuSo >= 8 && $chuSo <= 15;
    }

    public static function email(): ?string
    {
        return self::get('site_email');
    }

    public static function address(): ?string
    {
        return self::get('site_address');
    }

    public static function province(): ?string
    {
        return self::get('site_province');
    }
}
