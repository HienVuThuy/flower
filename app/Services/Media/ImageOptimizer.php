<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/** Sinh bản WebP nhiều cỡ cho MỘT ảnh, và cập nhật manifest. */
class ImageOptimizer
{
    public const CHAT_LUONG = 82;

    public function xuLyMot(string $path, bool $lamLai = false, int $chatLuong = self::CHAT_LUONG): bool
    {
        $disk = Storage::disk('public');

        if (! preg_match('/\.(jpe?g|png)$/i', $path) || ! $disk->exists($path)) {
            return false;
        }

        $duongDanThat = $disk->path($path);
        $kichThuoc = @getimagesize($duongDanThat);

        if (! $kichThuoc) {
            return false;
        }

        [$rong, $cao] = $kichThuoc;
        $ban = [];

        foreach (ResponsiveImage::WIDTHS as $w) {
            if ($rong < $w) {
                continue;
            }

            $dich = ResponsiveImage::FOLDER . "/{$w}/" . preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);

            if (! $lamLai && $disk->exists($dich)
                && $disk->lastModified($dich) >= $disk->lastModified($path)) {
                $ban[$w] = $dich;

                continue;
            }

            if ($this->sinhBan($duongDanThat, $disk->path($dich), $w, $rong, $cao, $chatLuong)) {
                $ban[$w] = $dich;
            }
        }

        $this->ghiManifest($path, [
            'width' => $rong,
            'height' => $cao,
            'webp' => $ban,
        ]);

        return true;
    }

    public function xoa(string $path): void
    {
        $disk = Storage::disk('public');

        foreach (ResponsiveImage::WIDTHS as $w) {
            $dich = ResponsiveImage::FOLDER . "/{$w}/" . preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);

            if ($disk->exists($dich)) {
                $disk->delete($dich);
            }
        }

        $this->ghiManifest($path, null);
    }

    private function ghiManifest(string $path, ?array $thongTin): void
    {
        $disk = Storage::disk('public');

        $khoa = \Illuminate\Support\Facades\Cache::lock('anh.manifest.ghi', 10);

        try {
            $khoa->block(5);

            $manifest = $disk->exists(ResponsiveImage::MANIFEST)
                ? (json_decode((string) $disk->get(ResponsiveImage::MANIFEST), true) ?: [])
                : [];

            if ($thongTin === null) {
                unset($manifest[$path]);
            } else {
                $manifest[$path] = $thongTin;
            }

            $disk->put(
                ResponsiveImage::MANIFEST,
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            );

            ResponsiveImage::quenManifest();
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            Log::warning('Không lấy được khoá để ghi manifest ảnh', ['path' => $path]);
        } finally {
            optional($khoa)->release();
        }
    }

    private function sinhBan(
        string $nguon,
        string $dich,
        int $rongDich,
        int $rongGoc,
        int $caoGoc,
        int $chatLuong,
    ): bool {
        $anh = match (strtolower(pathinfo($nguon, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($nguon),
            'png' => @imagecreatefrompng($nguon),
            default => false,
        };

        if (! $anh) {
            return false;
        }

        $caoDich = (int) round($caoGoc * ($rongDich / $rongGoc));
        $moi = imagecreatetruecolor($rongDich, $caoDich);

        imagealphablending($moi, false);
        imagesavealpha($moi, true);

        imagecopyresampled($moi, $anh, 0, 0, 0, 0, $rongDich, $caoDich, $rongGoc, $caoGoc);

        if (! is_dir(dirname($dich))) {
            mkdir(dirname($dich), 0755, true);
        }

        $ok = imagewebp($moi, $dich, $chatLuong);

        imagedestroy($anh);
        imagedestroy($moi);

        return $ok;
    }
}
