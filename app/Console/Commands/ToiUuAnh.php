<?php

namespace App\Console\Commands;

use App\Services\Media\ImageOptimizer;
use App\Services\Media\ResponsiveImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Sinh bản WebP nhiều kích cỡ cho toàn bộ ảnh của cửa hàng.
 * ============================================================
 * ĐÂY LÀ LỆNH QUÉT LẠI, KHÔNG PHẢI ĐƯỜNG CHÍNH.
 *
 * Từ nay ảnh admin tải lên được tối ưu NGAY lúc lưu — xem
 * App\Services\Media\ImageStore. Lệnh này còn lại ba việc:
 *
 *   1. bù cho ảnh cũ đã có trên đĩa từ trước khi có ImageStore;
 *   2. sinh lại toàn bộ khi đổi danh sách kích cỡ hoặc chất lượng nén
 *      (`--lam-lai`);
 *   3. dựng lại manifest nếu tệp đó bị mất.
 *
 * Nó KHÔNG còn tự cài đặt thuật toán resize: phần đó nằm ở
 * ImageOptimizer và dùng chung với đường tải lên. Hai bản riêng là hai
 * bản sẽ lệch nhau, và khi ấy ảnh cũ với ảnh mới trông khác nhau trên
 * cùng một trang.
 *
 * CHẠY LẠI ĐƯỢC NHIỀU LẦN: bỏ qua ảnh đã sinh và chưa đổi.
 */
class ToiUuAnh extends Command
{
    protected $signature = 'anh:toi-uu
                            {--lam-lai : Sinh lại cả những ảnh đã có}
                            {--chat-luong=82 : Chất lượng WebP, 0-100}';

    protected $description = 'Quét lại toàn bộ ảnh và sinh bản WebP còn thiếu';

    /**
     * Những thư mục ảnh do cửa hàng tải lên.
     *
     * `hero` THÊM VÀO SAU: ảnh khung lớn trang chủ vốn không có trong
     * danh sách này, nên nó chưa bao giờ được tối ưu — mà nó lại là ảnh
     * TO NHẤT và là thứ khách nhìn thấy đầu tiên.
     */
    private const THU_MUC = ['products', 'categories', 'promotions', 'hero'];

    public function handle(ImageOptimizer $optimizer): int
    {
        if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
            $this->error('PHP chưa bật extension GD (hoặc GD không hỗ trợ WebP).');
            $this->line('Mở php.ini, bỏ dấu ; ở dòng  extension=gd  rồi khởi động lại máy chủ.');

            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $chatLuong = max(1, min(100, (int) $this->option('chat-luong')));
        $lamLai = (bool) $this->option('lam-lai');

        $daXuLy = 0;
        $bytesGoc = 0;
        $bytesMoi = 0;

        foreach (self::THU_MUC as $thuMuc) {
            /*
             * allFiles() CHỨ KHÔNG files().
             *
             * LỖI ĐÃ SỬA: `files()` không đệ quy, nên nó không nhìn thấy
             * `products/gallery/` — toàn bộ ảnh phụ của sản phẩm chưa bao
             * giờ được tối ưu, và không có gì báo. Chỉ phát hiện khi ngồi
             * đối chiếu manifest với ảnh thật trên đĩa.
             */
            foreach ($disk->allFiles($thuMuc) as $path) {
                if (! preg_match('/\.(jpe?g|png)$/i', $path)) {
                    continue;
                }

                $truoc = $disk->size($path);

                if (! $optimizer->xuLyMot($path, $lamLai, $chatLuong)) {
                    $this->warn('  Bỏ qua (không đọc được): ' . $path);

                    continue;
                }

                $daXuLy++;
                $bytesGoc += $truoc;
                $bytesMoi += $this->tongBanWebp($path);

                if ($daXuLy % 10 === 0) {
                    $this->line("  ...đã xử lý {$daXuLy} ảnh");
                }
            }
        }

        $this->newLine();
        /*
         * NÓI RÕ CON SỐ NÀY ĐO CÁI GÌ.
         *
         * `$bytesMoi` là tổng của CẢ HAI bản (400px + 800px) cho mỗi
         * ảnh, còn trình duyệt chỉ tải MỘT bản. Nên đây là con số về chỗ
         * chiếm trên đĩa, KHÔNG phải mức giảm băng thông mà khách nhận
         * được — mức đó lớn hơn nhiều.
         *
         * Ghi "giảm 23%" trống không thì lần sau có người đọc và tưởng
         * việc tối ưu ảnh chỉ đáng 23%.
         */
        $this->info(sprintf(
            'Xong %d ảnh. Ảnh gốc %s; toàn bộ bản WebP (cả %s) %s.',
            $daXuLy,
            $this->doc($bytesGoc),
            implode(' + ', array_map(fn ($w) => $w . 'px', ResponsiveImage::WIDTHS)),
            $this->doc($bytesMoi),
        ));

        $this->line(
            '  Trình duyệt chỉ tải MỘT bản cho mỗi ảnh, nên mức giảm khách '
                . 'thật sự nhận được lớn hơn con số trên.'
        );

        return self::SUCCESS;
    }

    /** Tổng dung lượng các bản WebP đã sinh từ một ảnh gốc. */
    private function tongBanWebp(string $path): int
    {
        $disk = Storage::disk('public');
        $tong = 0;

        foreach (ResponsiveImage::WIDTHS as $w) {
            $dich = ResponsiveImage::FOLDER . "/{$w}/" . preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);

            if ($disk->exists($dich)) {
                $tong += $disk->size($dich);
            }
        }

        return $tong;
    }

    private function doc(int $bytes): string
    {
        return $bytes >= 1048576
            ? sprintf('%.1f MB', $bytes / 1048576)
            : sprintf('%.0f KB', $bytes / 1024);
    }
}
