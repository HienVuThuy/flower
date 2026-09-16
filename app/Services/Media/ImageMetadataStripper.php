<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Storage;

/**
 * Xoá metadata khỏi ảnh người dùng tải lên.
 * ⚠️ PHẢI XOAY ẢNH TRƯỚC KHI XOÁ EXIF.
 */
class ImageMetadataStripper
{
    private const CHAT_LUONG_JPEG = 90;

    public function tuoc(string $path): bool
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return false;
        }

        $duongDan = $disk->path($path);
        $duoi = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $anh = match ($duoi) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($duongDan),
            'png' => @imagecreatefrompng($duongDan),
            'webp' => @imagecreatefromwebp($duongDan),
            default => false,
        };

        if (! $anh) {
            return false;
        }

        $anh = $this->xoayTheoExif($anh, $duongDan, $duoi);

        if ($duoi !== 'jpg' && $duoi !== 'jpeg') {
            imagealphablending($anh, false);
            imagesavealpha($anh, true);
        }

        $tam = $duongDan . '.strip.tmp';

        $ok = match ($duoi) {
            'jpg', 'jpeg' => imagejpeg($anh, $tam, self::CHAT_LUONG_JPEG),
            'png' => imagepng($anh, $tam),
            'webp' => imagewebp($anh, $tam, self::CHAT_LUONG_JPEG),
            default => false,
        };

        imagedestroy($anh);

        if (! $ok || ! is_file($tam)) {
            @unlink($tam);

            return false;
        }

        return @rename($tam, $duongDan);
    }

    private function xoayTheoExif(\GdImage $anh, string $duongDan, string $duoi): \GdImage
    {
        if (! in_array($duoi, ['jpg', 'jpeg'], true) || ! function_exists('exif_read_data')) {
            return $anh;
        }

        $exif = @exif_read_data($duongDan);
        $huong = (int) ($exif['Orientation'] ?? 0);

        $goc = match ($huong) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($goc === 0) {
            return $anh;
        }

        $xoay = @imagerotate($anh, $goc, 0);

        if (! $xoay) {
            return $anh;
        }

        imagedestroy($anh);

        return $xoay;
    }

    public function metadataConLai(string $path): array
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path) || ! function_exists('exif_read_data')) {
            return [];
        }

        $exif = @exif_read_data($disk->path($path));

        if (! is_array($exif)) {
            return [];
        }

        $nguyHiem = [];

        foreach (['GPSLatitude', 'GPSLongitude', 'GPSAltitude'] as $k) {
            if (isset($exif[$k]) || isset($exif['GPS'][$k])) {
                $nguyHiem[] = $k;
            }
        }

        foreach (['Make', 'Model', 'DateTimeOriginal', 'Artist', 'Copyright', 'Software'] as $k) {
            if (! empty($exif[$k])) {
                $nguyHiem[] = $k;
            }
        }

        return $nguyHiem;
    }
}
