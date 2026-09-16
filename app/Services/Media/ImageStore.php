<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/** MỘT CỬA DUY NHẤT để lưu ảnh do người dùng tải lên. */
class ImageStore
{
    public function __construct(
        private readonly ImageOptimizer $optimizer,
        private readonly ImageMetadataStripper $stripper,
    ) {
    }

    public function luu(UploadedFile $file, string $thuMuc): string
    {
        $path = $file->store($thuMuc, 'public');

        try {
            $this->stripper->tuoc($path);
        } catch (\Throwable $e) {
            Log::error('KHÔNG TƯỚC ĐƯỢC METADATA của ảnh vừa tải lên', [
                'path' => $path,
                'loi' => $e->getMessage(),
            ]);
        }

        $this->toiUu($path);

        return $path;
    }

    public function xoa(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('public')->delete($path);

        try {
            $this->optimizer->xoa($path);
        } catch (\Throwable $e) {
            Log::warning('Không dọn được bản tối ưu của ảnh đã xoá', [
                'path' => $path,
                'loi' => $e->getMessage(),
            ]);
        }
    }

    public function toiUu(string $path): void
    {
        try {
            $this->optimizer->xuLyMot($path);
        } catch (\Throwable $e) {
            Log::warning('Không tối ưu được ảnh vừa tải lên', [
                'path' => $path,
                'loi' => $e->getMessage(),
            ]);
        }
    }
}
