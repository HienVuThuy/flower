<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductBlock;
use App\Services\Media\HtmlSanitizer;
use App\Services\Media\ImageStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Dựng lại phần mô tả chi tiết theo khối, đúng như biểu mẫu vừa gửi. */
class ProductBlockService
{
    public function __construct(
        private readonly ImageStore $anh,
        private readonly HtmlSanitizer $locHtml,
    ) {
    }

    public function sync(Product $product, array $rows): array
    {
        $daCo = $product->blocks()->get()->keyBy('id');
        $stored = [];
        $giuLai = [];
        $thuTu = 0;

        foreach ($rows as $row) {
            $kind = ($row['kind'] ?? '') === ProductBlock::ANH ? ProductBlock::ANH : ProductBlock::CHU;
            $cu = isset($row['id']) ? $daCo->get((int) $row['id']) : null;

            $khoi = $kind === ProductBlock::ANH
                ? $this->khoiAnh($product, $row, $cu, $stored)
                : $this->khoiChu($product, $row, $cu);

            if ($khoi === null) {
                continue;
            }

            $khoi->forceFill(['sort_order' => $thuTu++])->save();
            $giuLai[] = $khoi->id;
        }

        foreach ($daCo as $khoi) {
            if (in_array($khoi->id, $giuLai, true)) {
                continue;
            }

            $this->xoaAnh($khoi);
            $khoi->delete();
        }

        return $stored;
    }

    public function rollback(array $paths): void
    {
        foreach ($paths as $path) {
            $this->anh->xoa($path);
        }
    }

    private function khoiChu(Product $product, array $row, ?ProductBlock $cu): ?ProductBlock
    {
        $chu = $this->locHtml->lamSach((string) ($row['body'] ?? ''));

        if (trim(strip_tags($chu)) === '') {
            if ($cu) {
                $this->xoaAnh($cu);
                $cu->delete();
            }

            return null;
        }

        if ($cu) {
            $this->xoaAnh($cu);

            $cu->forceFill([
                'kind' => ProductBlock::CHU,
                'body' => $chu,
                'image_path' => null,
                'caption' => null,
            ])->save();

            return $cu;
        }

        return $product->blocks()->create(['kind' => ProductBlock::CHU, 'body' => $chu]);
    }

    private function khoiAnh(Product $product, array $row, ?ProductBlock $cu, array &$stored): ?ProductBlock
    {
        $file = $row['image'] ?? null;
        $caption = trim((string) ($row['caption'] ?? '')) ?: null;

        $duong = $cu?->image_path;

        if ($file instanceof UploadedFile) {
            $moi = $this->anh->luu($file, 'products/blocks');
            $stored[] = $moi;

            if ($duong) {
                $this->anh->xoa($duong);
            }

            $duong = $moi;
        }

        if ($duong === null) {
            if ($cu) {
                $cu->delete();
            }

            return null;
        }

        if ($cu) {
            $cu->forceFill([
                'kind' => ProductBlock::ANH,
                'body' => null,
                'image_path' => $duong,
                'caption' => $caption,
            ])->save();

            return $cu;
        }

        return $product->blocks()->create([
            'kind' => ProductBlock::ANH,
            'image_path' => $duong,
            'caption' => $caption,
        ]);
    }

    private function xoaAnh(ProductBlock $khoi): void
    {
        if ($khoi->image_path) {
            $this->anh->xoa($khoi->image_path);
        }
    }
}
