<?php

namespace App\Services\Theme;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Bộ ảnh hero do ADMIN tự khai, cho từng theme. */
class HeroImages
{
    public const KEY = 'hero_images';

    public const DIR = 'hero';

    public const MAX = 10;

    public const MIMES = ['jpg', 'jpeg', 'png', 'webp'];

    public const MAX_KB = 3072;

    public static function all(): array
    {
        $raw = Setting::get(self::KEY);

        if (! $raw) {
            return [];
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    public static function forTheme(string $themeKey): array
    {
        $rows = self::all()[$themeKey] ?? [];
        $disk = Storage::disk('public');
        $out = [];

        foreach ($rows as $row) {
            $path = is_array($row) ? ($row['path'] ?? '') : '';

            if ($path === '' || ! $disk->exists($path)) {
                continue;
            }

            $out[] = [
                'path' => $path,
                'alt' => is_array($row) ? (string) ($row['alt'] ?? '') : '',
                'url' => $disk->url($path),
            ];
        }

        return array_slice($out, 0, self::MAX);
    }

    public static function saveTheme(string $themeKey, array $keep, array $files): int
    {
        $all = self::all();
        $before = $all[$themeKey] ?? [];

        $rows = [];

        foreach ($keep as $item) {
            $path = trim((string) ($item['path'] ?? ''));

            if ($path === '' || ! self::isKnownPath($before, $path)) {
                continue;
            }

            $rows[] = [
                'path' => $path,
                'alt' => trim((string) ($item['alt'] ?? '')),
            ];
        }

        foreach ($files as $file) {
            if (count($rows) >= self::MAX) {
                break;
            }

            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $rows[] = [
                'path' => app(\App\Services\Media\ImageStore::class)->luu($file, self::DIR),
                'alt' => '',
            ];
        }

        $rows = array_slice($rows, 0, self::MAX);

        self::deleteOrphans($before, $rows);

        $all[$themeKey] = $rows;

        $all = array_filter($all, fn ($v) => ! empty($v));

        Setting::set(self::KEY, $all ? json_encode($all, JSON_UNESCAPED_UNICODE) : null);

        return count($rows);
    }

    private static function isKnownPath(array $rows, string $path): bool
    {
        foreach ($rows as $row) {
            if (is_array($row) && ($row['path'] ?? null) === $path) {
                return true;
            }
        }

        return false;
    }

    private static function deleteOrphans(array $before, array $after): void
    {
        $keptPaths = array_column($after, 'path');
        $disk = Storage::disk('public');

        foreach ($before as $row) {
            $path = is_array($row) ? ($row['path'] ?? '') : '';

            if ($path === '' || in_array($path, $keptPaths, true)) {
                continue;
            }

            if (str_starts_with($path, self::DIR . '/') && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }
}
