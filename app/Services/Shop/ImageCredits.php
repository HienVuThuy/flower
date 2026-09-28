<?php

namespace App\Services\Shop;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/** Gom phần ghi công tác giả ảnh từ các tệp credits.json. */
class ImageCredits
{
    private const SOURCES = [
        [
            'label' => 'Ảnh sản phẩm',
            'path' => 'products/credits.json',
            'disk' => 'public',
        ],
        [
            'label' => 'Ảnh danh mục',
            'path' => 'categories/credits.json',
            'disk' => 'public',
        ],
        [
            'label' => 'Ảnh bài Cẩm nang',
            'path' => 'blog/credits.json',
            'disk' => 'public',
        ],
        [
            'label' => 'Ảnh minh hoạ nhu cầu',
            'path' => 'resources/images/catalog/credits.json',
            'disk' => null,
        ],
        [
            'label' => 'Ảnh khung lớn trang chủ',
            'path' => 'resources/images/hero/credits.json',
            'disk' => null,
        ],
    ];

    public function grouped(): Collection
    {
        return collect(self::SOURCES)
            ->map(fn (array $src) => [
                'label' => $src['label'],
                'items' => $this->read($src['path'], $src['disk']),
            ])
            ->filter(fn (array $g) => $g['items']->isNotEmpty())
            ->values();
    }

    public function total(): int
    {
        return $this->grouped()->sum(fn (array $g) => $g['items']->count());
    }

    private function read(string $path, ?string $disk): Collection
    {
        $raw = $disk === 'public'
            ? (Storage::disk('public')->exists($path) ? Storage::disk('public')->get($path) : null)
            : (is_file(base_path($path)) ? file_get_contents(base_path($path)) : null);

        if (! $raw) {
            return collect();
        }

        $rows = json_decode($raw, true);

        if (! is_array($rows)) {
            return collect();
        }

        return collect($rows)
            ->map(fn ($row) => [
                'title' => trim((string) ($row['title'] ?? '')) ?: '(không có tiêu đề)',
                'author' => trim((string) ($row['author'] ?? '')) ?: 'Không ghi tên',
                'license' => trim((string) ($row['license'] ?? '')),
                'licenseUrl' => trim((string) ($row['licenseUrl'] ?? '')),
                'pageUrl' => trim((string) ($row['pageUrl'] ?? '')),
                'used' => trim((string) ($row['hint'] ?? $row['slug'] ?? '')),
            ])
            ->filter(fn (array $r) => $r['license'] !== '')
            ->sortBy('author')
            ->values();
    }
}
