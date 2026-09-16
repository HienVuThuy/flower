<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Media\ImageStore;
use App\Services\Media\VideoLink;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Quản lý thư viện ảnh phụ của sản phẩm. */
class ProductImageService
{
    public function __construct(private readonly ImageStore $anh)
    {
    }

    public function attach(Product $product, array $files): array
    {
        $stored = [];

        $nextOrder = (int) $product->images()->max('sort_order') + 1;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $this->anh->luu($file, 'products/gallery');
            $stored[] = $path;

            $product->images()->create([
                'path' => $path,
                'alt' => $product->name,
                'sort_order' => $nextOrder++,
            ]);
        }

        return $stored;
    }

    public function attachVideos(Product $product, array $links = [], array $files = []): array
    {
        $stored = [];

        $nextOrder = (int) $product->media()->max('sort_order') + 1;

        foreach ($links as $link) {
            $nhung = VideoLink::nhung(is_string($link) ? $link : null);

            if ($nhung === null) {
                continue;
            }

            $product->media()->create([
                'kind' => ProductImage::VIDEO,
                'video_url' => $nhung,
                'alt' => $product->name,
                'sort_order' => $nextOrder++,
            ]);
        }

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store('products/video', 'public');
            $stored[] = $path;

            $product->media()->create([
                'kind' => ProductImage::VIDEO,
                'path' => $path,
                'alt' => $product->name,
                'sort_order' => $nextOrder++,
            ]);
        }

        return $stored;
    }

    public function detach(Product $product, array $imageIds): void
    {
        if (! $imageIds) {
            return;
        }

        $items = $product->media()->whereIn('id', $imageIds)->get();

        foreach ($items as $item) {
            if ($item->path) {
                $this->anh->xoa($item->path);
            }

            $item->delete();
        }
    }

    public function purge(Product $product): void
    {
        foreach ($product->media as $item) {
            if ($item->path) {
                $this->anh->xoa($item->path);
            }
        }
    }

    public function rollback(array $paths): void
    {
        foreach ($paths as $path) {
            $this->anh->xoa($path);
        }
    }
}
