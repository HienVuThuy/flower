<?php

namespace App\Services\Theme;

use App\Models\Setting;
use Illuminate\Support\Facades\Vite;

/**
 * Nơi DUY NHẤT biết theme nào đang bật và mỗi theme gồm những gì.
 *
 * Controller/Blade không được đọc thẳng config('theme.*') hay
 * Setting::get('theme') nữa — đi qua đây để nếu sau này đổi cách
 * lưu (ví dụ theme theo lịch, theo chiến dịch) thì chỉ sửa một chỗ.
 */
class ThemeRegistry
{
    /** @var array<string, array>|null */
    private ?array $themes = null;

    /** Toàn bộ theme khả dụng, giữ nguyên thứ tự khai báo. */
    public function all(): array
    {
        return $this->themes ??= config('theme.themes', []);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * Key của theme đang bật. Ưu tiên giá trị admin lưu trong DB,
     * rơi về config nếu chưa đặt, và rơi tiếp về 'default' nếu giá
     * trị đã lưu trỏ tới một theme không còn tồn tại.
     */
    public function activeKey(): string
    {
        $key = Setting::get('theme', config('theme.active', 'default'));

        return $this->exists($key) ? $key : 'default';
    }

    public function get(string $key): array
    {
        return $this->all()[$key] ?? $this->all()['default'] ?? [];
    }

    public function active(): array
    {
        return $this->get($this->activeKey());
    }

    public function label(?string $key): string
    {
        if ($key === null) {
            return '—';
        }

        return $this->get($key)['label'] ?? $key;
    }

    /**
     * Tên module hiệu ứng của theme đang bật (hoặc null nếu theme
     * không có chuyển động). Được in ra data-attribute để JS biết
     * cần nạp động module nào.
     */
    public function activeEffect(): ?string
    {
        return $this->active()['effect'] ?? null;
    }

    /**
     * Bộ ảnh hero của theme đang bật.
     *
     * HAI NGUỒN, ưu tiên theo thứ tự:
     *   1. ảnh admin tự tải lên (bảng settings + storage/app/public/hero)
     *   2. bộ mặc định khai trong config/theme.php
     *
     * Trả về sẵn `url` chứ không trả `file`, vì hai nguồn dựng URL theo
     * hai cách khác nhau (Vite::asset với ảnh trong resources, còn ảnh
     * admin tải lên thì qua disk `public`). Để view tự phân biệt là bắt
     * nó biết chuyện không phải việc của nó.
     *
     * @return array<int, array{url: string, alt: string}>
     */
    public function heroImages(): array
    {
        $custom = HeroImages::forTheme($this->activeKey());

        if ($custom) {
            return array_map(
                fn (array $i) => ['url' => $i['url'], 'alt' => $i['alt']],
                $custom,
            );
        }

        $images = $this->active()['hero'] ?? [];

        $out = [];

        foreach ($images as $item) {
            // Lọc bỏ mục khai thiếu để view khỏi phải tự phòng thủ.
            if (! is_array($item) || empty($item['file'])) {
                continue;
            }

            $out[] = [
                'url' => Vite::asset('resources/images/hero/' . $item['file']),
                'alt' => (string) ($item['alt'] ?? ''),
            ];
        }

        return $out;
    }

    /** Ảnh admin đã khai cho MỘT theme bất kỳ — trang Cài đặt dùng. */
    public function customHeroImages(string $themeKey): array
    {
        return HeroImages::forTheme($themeKey);
    }

    /** Dạng phẳng cho các ô <select> trong admin: [key => label]. */
    public function options(): array
    {
        return array_map(fn (array $t) => $t['label'], $this->all());
    }
}
