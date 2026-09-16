<?php

namespace App\Services\Shop;

use App\Models\Setting;

/** Cam kết dịch vụ của cửa hàng. */
class ServiceCommitments
{
    public const KEY = 'service_commitments';

    public const MAX = 6;

    public static function icons(): array
    {
        return [
            'check-circle' => 'Dấu tích',
            'shield-lock' => 'Khiên bảo đảm',
            'telephone' => 'Điện thoại',
            'flower1' => 'Bông hoa',
            'flower2' => 'Cành lá',
            'geo-alt' => 'Định vị',
            'arrow-repeat' => 'Vòng lặp',
            'tags' => 'Thẻ giá',
            'envelope-paper' => 'Thiệp',
        ];
    }

    public static function all(): array
    {
        $raw = Setting::get(self::KEY);

        if (! $raw) {
            return [];
        }

        $rows = json_decode($raw, true);

        if (! is_array($rows)) {
            return [];
        }

        $icons = self::icons();
        $out = [];

        foreach ($rows as $row) {
            $title = trim($row['title'] ?? '');

            if ($title === '') {
                continue;
            }

            $out[] = [
                'icon' => isset($icons[$row['icon'] ?? '']) ? $row['icon'] : 'check-circle',
                'title' => $title,
                'note' => trim($row['note'] ?? ''),
            ];
        }

        return array_slice($out, 0, self::MAX);
    }

    public static function save(array $rows): void
    {
        $icons = self::icons();
        $clean = [];

        foreach (array_slice($rows, 0, self::MAX) as $row) {
            $title = trim((string) ($row['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $clean[] = [
                'icon' => isset($icons[$row['icon'] ?? '']) ? $row['icon'] : 'check-circle',
                'title' => $title,
                'note' => trim((string) ($row['note'] ?? '')),
            ];
        }

        Setting::set(self::KEY, $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE) : null);
    }
}
