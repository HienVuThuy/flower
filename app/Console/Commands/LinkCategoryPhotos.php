<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Gán ảnh đã tải bằng tools/fetch-category-photos.mjs vào cột categories.image. */
class LinkCategoryPhotos extends Command
{
    protected $signature = 'categories:link-photos
                            {--force : Ghi đè cả những danh mục đã có ảnh}';

    protected $description = 'Gán ảnh trong storage/app/public/categories vào categories.image theo slug';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $creditsPath = 'categories/credits.json';

        if (! $disk->exists($creditsPath)) {
            $this->error('Chưa có categories/credits.json. Chạy trước: node tools/fetch-category-photos.mjs');

            return self::FAILURE;
        }

        $credits = json_decode($disk->get($creditsPath), true);

        if (! is_array($credits)) {
            $this->error('categories/credits.json hỏng.');

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

            $category = Category::where('slug', $slug)->first();

            if (! $category) {
                $this->warn(" Bỏ qua: không có danh mục slug '{$slug}'.");
                $missing++;

                continue;
            }

            if (! $disk->exists($file)) {
                $this->warn(" Bỏ qua '{$slug}': không thấy tệp {$file}.");
                $missing++;

                continue;
            }

            if ($category->image && ! $this->option('force')) {
                $skipped++;

                continue;
            }

            $category->image = $file;
            $category->save();

            $this->line(" {$slug} -> {$file}");
            $linked++;
        }

        $this->newLine();
        $this->info("Đã gán: {$linked} | bỏ qua: {$skipped} | thiếu: {$missing}");

        return self::SUCCESS;
    }
}
