<?php

namespace App\Services\Shop;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Gom phần ghi công tác giả ảnh từ các tệp credits.json.
 * ============================================================
 * VÌ SAO PHẢI CÓ TRANG NÀY:
 * Ảnh trong dự án dùng giấy phép CC BY và CC BY-SA. Hai giấy phép đó
 * CHO PHÉP dùng thương mại và sửa đổi, nhưng BẮT BUỘC ghi tên tác giả
 * "theo cách hợp lý với phương tiện đang dùng". Với một website thì ghi
 * trong một tệp Markdown nằm trong mã nguồn là chưa đủ — người xem
 * website không nhìn thấy tệp đó bao giờ.
 *
 * ĐỌC TỪ credits.json, KHÔNG CHÉP TAY.
 * Các tệp đó do chính script tải ảnh sinh ra, nên danh sách ở đây luôn
 * khớp với ảnh thật đang dùng. Chép tay sang Blade thì tải thêm ảnh mới
 * là trang này lạc hậu ngay, mà lạc hậu ở đây nghĩa là thiếu ghi công
 * cho một tác giả.
 */
class ImageCredits
{
    /**
     * Các nguồn ghi công, theo nhóm.
     *
     * Mỗi mục: nhãn nhóm => đường dẫn tệp credits.json.
     * `disk` = 'public' đọc từ storage; null đọc từ resources/.
     */
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

    /**
     * @return Collection<int, array{label: string, items: Collection}>
     */
    public function grouped(): Collection
    {
        return collect(self::SOURCES)
            ->map(fn (array $src) => [
                'label' => $src['label'],
                'items' => $this->read($src['path'], $src['disk']),
            ])
            // Nhóm không có dữ liệu thì bỏ hẳn, không hiện tiêu đề rỗng.
            ->filter(fn (array $g) => $g['items']->isNotEmpty())
            ->values();
    }

    /** Tổng số ảnh đang được ghi công. */
    public function total(): int
    {
        return $this->grouped()->sum(fn (array $g) => $g['items']->count());
    }

    /**
     * Đọc một tệp credits.json và chuẩn hoá về cùng một dạng.
     *
     * Ba tệp được sinh bởi hai script khác nhau nên tên khoá hơi lệch
     * (`hint` với `slug`). Chuẩn hoá ở đây để Blade chỉ phải biết MỘT
     * dạng dữ liệu.
     */
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
            // Không có giấy phép thì không phải mục ghi công hợp lệ.
            ->filter(fn (array $r) => $r['license'] !== '')
            ->sortBy('author')
            ->values();
    }
}
