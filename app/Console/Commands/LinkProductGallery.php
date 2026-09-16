<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Gán ảnh phụ đã tải bằng tools/fetch-product-gallery.mjs vào bảng `product_images`. */
class LinkProductGallery extends Command
{
    protected $signature = 'products:link-gallery
                            {--fresh : Xoá ảnh phụ cũ do script tải về trước khi gán lại}';

    protected $description = 'Gán ảnh trong storage/app/public/products/gallery vào product_images theo slug';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $creditsPath = 'products/gallery/credits.json';

        if (! $disk->exists($creditsPath)) {
            $this->error('Chưa có products/gallery/credits.json. Chạy trước: node tools/fetch-product-gallery.mjs');

            return self::FAILURE;
        }

        $credits = json_decode($disk->get($creditsPath), true);

        if (! is_array($credits)) {
            $this->error('credits.json hỏng.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $xoa = \App\Models\ProductImage::where('path', 'like', 'products/gallery/%')->delete();
            $this->line("Đã xoá {$xoa} ảnh phụ cũ do script tải về.");
        }

        $added = 0;
        $skipped = 0;

        $lac = [];

        foreach ($credits as $row) {
            $slug = $row['slug'] ?? null;
            $file = $row['file'] ?? null;

            if (! $slug || ! $file) {
                continue;
            }

            if (! $disk->exists($file)) {
                $lac[] = $file . ' (thiếu tệp)';

                continue;
            }

            $product = Product::where('slug', $slug)->first();

            if (! $product) {
                $lac[] = $slug . ' (không có sản phẩm nào mang slug này)';

                continue;
            }

            if ($product->images()->where('path', $file)->exists()) {
                $skipped++;

                continue;
            }

            $product->images()->create([
                'path' => $file,

                'alt' => $product->name . ' — ảnh ' . (($row['sort'] ?? 1) + 1),
                'sort_order' => (int) ($row['sort'] ?? 1),
            ]);

            $added++;
        }

        $this->newLine();
        $this->info("Đã thêm: {$added} | bỏ qua (đã có): {$skipped}");

        if ($lac !== []) {
            $this->newLine();
            $this->warn('Không gán được ' . count($lac) . ' dòng:');

            foreach (array_unique($lac) as $m) {
                $this->line('  - ' . $m);
            }

            $this->line('Sửa slug trong tools/lib/product-targets.mjs rồi chạy lại.');
        }

        return self::SUCCESS;
    }
}
