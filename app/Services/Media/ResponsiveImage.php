<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/** Ảnh nhiều kích cỡ: chọn đúng bản cho đúng chỗ hiển thị. */
class ResponsiveImage
{
    public const WIDTHS = [400, 800];

    public const FOLDER = 'rp';

    public const MANIFEST = 'rp/manifest.json';

    public function info(?string $path): ?array
    {
        if (! $path) {
            return null;
        }

        return $this->manifest()[$path] ?? null;
    }

    public function webpSrcset(?string $path): ?string
    {
        $info = $this->info($path);

        if (! $info || empty($info['webp'])) {
            return null;
        }

        $phan = [];

        foreach ($info['webp'] as $w => $tep) {
            $phan[] = Storage::url($tep).' '.$w.'w';
        }

        return implode(', ', $phan);
    }

    private function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        return $this->manifest = Cache::rememberForever('anh.manifest', function () {
            $disk = Storage::disk('public');

            if (! $disk->exists(self::MANIFEST)) {
                return [];
            }

            $json = json_decode((string) $disk->get(self::MANIFEST), true);

            return is_array($json) ? $json : [];
        });
    }

    private ?array $manifest = null;

    public static function quenManifest(): void
    {
        Cache::forget('anh.manifest');
    }
}
