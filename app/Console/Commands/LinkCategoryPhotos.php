<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Gán ảnh đã tải bằng tools/fetch-category-photos.mjs vào cột
 * categories.image.
 *
 * TÁCH RIÊNG khỏi script tải ảnh vì hai việc khác nhau: script Node chỉ
 * biết tệp, còn việc ghi vào cơ sở dữ liệu phải đi qua Eloquent để không
 * lách qua $fillable và các quy tắc của model.
 *
 * MẶC ĐỊNH KHÔNG GHI ĐÈ ảnh danh mục đã có — ảnh do cửa hàng tự tải lên
 * bao giờ cũng đúng hơn ảnh stock tải về. Muốn ghi đè thì --force.
 */
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

            /*
             * Kiểm tra tệp CÓ THẬT trước khi ghi vào cơ sở dữ liệu.
             *
             * credits.json là thứ script Node ghi ra; tệp ảnh có thể đã bị
             * xoá tay sau đó. Ghi bừa một đường dẫn chết thì thẻ danh mục
             * hiện ô ảnh vỡ — tệ hơn hẳn hình lá giữ chỗ.
             */
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
