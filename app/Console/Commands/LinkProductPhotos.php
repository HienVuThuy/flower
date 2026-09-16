<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Gán ảnh đã tải bằng tools/fetch-product-photos.mjs vào cột products.main_image. */
class LinkProductPhotos extends Command
{
    protected $signature = 'products:link-photos
                            {--force : Ghi đè cả những sản phẩm đã có ảnh}';

    protected $description = 'Gán ảnh trong storage/app/public/products vào main_image theo slug';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $creditsPath = 'products/credits.json';

        if (! $disk->exists($creditsPath)) {
            $this->error('Chưa có products/credits.json. Chạy trước: node tools/fetch-product-photos.mjs');

            return self::FAILURE;
        }

        $credits = json_decode($disk->get($creditsPath), true);

        if (! is_array($credits)) {
            $this->error('credits.json hỏng.');

            return self::FAILURE;
        }

        $linked = 0;
        $skipped = 0;
        $missing = 0;

        foreach ($credits as $row) {
            $slug = $row['slug'] ?? null;
            $file = $row['file'] ?? null;

            if (! $slug || ! $file) {
                continue;
            }

            $product = Product::where('slug', $slug)->first();

            if (! $product) {
                $this->warn("  không có sản phẩm nào slug = {$slug}");
                $missing++;

                continue;
            }

            if (! $disk->exists($file)) {
                $this->warn("  thiếu tệp {$file}");
                $missing++;

                continue;
            }

            if ($product->main_image && ! $this->option('force')) {
                $this->line("  bỏ qua {$slug} — đã có ảnh (dùng --force để ghi đè)");
                $skipped++;

                continue;
            }

            $product->main_image = $file;
            $product->save();

            $this->info("  {$slug} -> {$file}");
            $linked++;
        }

        $this->newLine();
        $this->line("Đã gán: {$linked} | bỏ qua: {$skipped} | thiếu: {$missing}");

        return self::SUCCESS;
    }
}
