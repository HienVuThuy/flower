<?php

namespace App\Console\Commands;

use App\Services\Media\ImageOptimizer;
use App\Services\Media\ResponsiveImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Sinh bản WebP nhiều kích cỡ cho toàn bộ ảnh của cửa hàng. */
class ToiUuAnh extends Command
{
    protected $signature = 'anh:toi-uu
                            {--lam-lai : Sinh lại cả những ảnh đã có}
                            {--chat-luong=82 : Chất lượng WebP, 0-100}';

    protected $description = 'Quét lại toàn bộ ảnh và sinh bản WebP còn thiếu';

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
