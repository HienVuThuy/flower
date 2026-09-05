<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Gán ảnh phụ đã tải bằng tools/fetch-product-gallery.mjs vào bảng
 * `product_images`.
 *
 * TÁCH RIÊNG khỏi script tải ảnh, cùng lý do như `products:link-photos`:
 * script Node chỉ biết tệp, còn việc ghi vào cơ sở dữ liệu phải đi qua
 * Eloquent để không lách qua $fillable và các quy tắc của model.
 *
 * KHÔNG GHI ĐÈ ảnh do cửa hàng tự thêm. Lệnh này chỉ thêm những đường
 * dẫn CHƯA có trong bảng, nên chạy lại nhiều lần cũng không sinh ra bản
 * ghi trùng — và ảnh admin tự tải lên không bao giờ bị đụng tới.
 */
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
            /*
             * CHỈ XOÁ ẢNH DO SCRIPT TẢI VỀ — nhận ra qua tiền tố đường
             * dẫn `products/gallery/`.
             *
             * Xoá sạch bảng thì mất luôn ảnh admin tự tải lên, và không
             * có cách nào lấy lại. `--fresh` là để chạy lại script, không
             * phải để dọn bảng.
             */
            $xoa = \App\Models\ProductImage::where('path', 'like', 'products/gallery/%')->delete();
            $this->line("Đã xoá {$xoa} ảnh phụ cũ do script tải về.");
        }

        $added = 0;
        $skipped = 0;

        /*
         * NÊU TÊN SLUG LẠC, KHÔNG CHỈ ĐẾM.
         *
         * Lệnh gán ảnh đại diện báo "thiếu: 2" suốt từ đầu và không ai
         * biết đó là gì. Hoá ra hai slug trong danh sách truy vấn không
         * khớp sản phẩm nào: `monstera-deliciosa` thay vì
         * `monstera-deliciosa-chau-gom`, và `cay-luoi-ho-vang-vien-de-ban`
         * thay vì `luoi-ho-vang-vien-de-ban`.
         *
         * Hai sản phẩm đó lặng lẽ không bao giờ nhận được ảnh nào, và một
         * con số đếm thì không đủ để ai đi tìm.
         *
         * @var list<string>
         */
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

            // Đã có rồi thì thôi — chạy lại lệnh không được sinh bản trùng.
            if ($product->images()->where('path', $file)->exists()) {
                $skipped++;

                continue;
            }

            $product->images()->create([
                'path' => $file,

                /*
                 * ALT MÔ TẢ SẢN PHẨM, không mô tả bức ảnh gốc.
                 *
                 * Tiêu đề ảnh Openverse là tiếng Anh và thường là tên
                 * khoa học hoặc tên tệp máy ảnh ("DSC_0421"). Đọc lên
                 * cho người dùng trình đọc màn hình thì vô nghĩa. Tên
                 * sản phẩm kèm số thứ tự mới nói đúng thứ họ cần biết:
                 * đây là ảnh thứ mấy của món nào.
                 */
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
