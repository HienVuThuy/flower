<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Viết lại bảng ghi công ảnh trong ASSETS.md từ các tệp credits.json. */
class SyncAssetCredits extends Command
{
    protected $signature = 'assets:sync-credits {--check : Chỉ báo lệch, không ghi}';

    protected $description = 'Dựng lại bảng ghi công ảnh trong ASSETS.md từ credits.json';

    private const GROUPS = [
        'products' => ['Ảnh sản phẩm', 'products/credits.json', 'Sản phẩm'],
        'gallery' => ['Ảnh phụ trong thư viện', 'products/gallery/credits.json', 'Sản phẩm'],
        'categories' => ['Ảnh danh mục', 'categories/credits.json', 'Danh mục'],
        'promotions' => ['Ảnh nền banner', 'promotions/credits.json', 'Chủ đề'],
    ];

    public function handle(): int
    {
        $path = base_path('ASSETS.md');
        $before = file_get_contents($path);
        $after = $before;

        foreach (self::GROUPS as $key => [$label, $file, $column]) {
            $rows = $this->rows($file, $column);

            if ($rows === null) {
                $this->warn("Bỏ qua {$label}: chưa có {$file}.");

                continue;
            }

            $open = "<!-- credits:{$key} -->";
            $close = "<!-- /credits:{$key} -->";

            if (! str_contains($after, $open) || ! str_contains($after, $close)) {
                $this->error("ASSETS.md thiếu mốc {$open} … {$close}.");

                return self::FAILURE;
            }

            $after = preg_replace(
                '/'.preg_quote($open, '/').'.*?'.preg_quote($close, '/').'/s',
                $open."\n".$rows."\n".$close,
                $after,
                1
            );

            $this->line(sprintf(' %-14s %d ảnh', $label, substr_count($rows, "\n") - 1));
        }

        if ($after === $before) {
            $this->info('ASSETS.md đã khớp.');

            return self::SUCCESS;
        }

        if ($this->option('check')) {
            $this->error('ASSETS.md LỆCH so với credits.json. Chạy: php artisan assets:sync-credits');

            return self::FAILURE;
        }

        file_put_contents($path, $after);
        $this->info('Đã cập nhật ASSETS.md.');

        return self::SUCCESS;
    }

    private function rows(string $file, string $column): ?string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($file)) {
            return null;
        }

        $credits = json_decode($disk->get($file), true);

        if (! is_array($credits)) {
            return null;
        }

        usort($credits, fn ($a, $b) => strcmp($a['file'] ?? '', $b['file'] ?? ''));

        $out = "| Tệp | {$column} | Tác giả | Giấy phép | Nguồn |\n|---|---|---|---|---|";

        foreach ($credits as $row) {
            $out .= sprintf(
                "\n| `%s` | %s | %s | %s | [%s](%s) |",
                basename($row['file'] ?? ''),
                $this->cell($row['slug'] ?? ''),
                $this->cell($row['author'] ?? 'Không rõ'),
                $this->cell($row['license'] ?? '?'),
                $this->cell($row['title'] ?? 'Nguồn'),
                $row['pageUrl'] ?? '#',
            );
        }

        return $out;
    }

    private function cell(string $value): string
    {
        return str_replace(['|', "\n"], ['\|', ' '], trim($value));
    }
}
