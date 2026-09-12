<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductBlock;
use App\Services\Media\HtmlSanitizer;
use App\Services\Media\ImageStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Dựng lại phần mô tả chi tiết theo khối, đúng như biểu mẫu vừa gửi.
 * ============================================================
 * BIỂU MẪU LÀ TOÀN BỘ SỰ THẬT: những khối không có trong lần gửi này bị xoá,
 * kèm cả tệp ảnh của chúng. Cách còn lại — chỉ thêm và sửa — là mỗi lần admin
 * xoá một khối thì ảnh của nó ở lại trên đĩa mãi mãi, không ai trỏ tới.
 *
 * THỨ TỰ LẤY THEO THỨ TỰ GỬI LÊN, không lấy con số người dùng nhập: ô "số thứ
 * tự" gõ tay là chỗ để hai khối cùng mang số 3, và lúc đó thứ tự hiện ra do
 * database quyết định.
 *
 * CHỮ ĐI QUA HtmlSanitizer — cùng bộ lọc với mô tả cũ. Không có ngoại lệ nào
 * cho "admin thì tin được": tài khoản admin bị chiếm là lúc người ta cần cái
 * bộ lọc này nhất.
 */
class ProductBlockService
{
    public function __construct(
        private readonly ImageStore $anh,
        private readonly HtmlSanitizer $locHtml,
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows  mỗi dòng: kind, body, caption, id, image (UploadedFile)
     * @return list<string> tệp vừa lưu, để rollback nếu transaction hỏng
     */
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

        // Khối cũ không còn trong lần gửi này: xoá cả bản ghi lẫn tệp ảnh.
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
            Storage::disk('public')->delete($path);
        }
    }

    private function khoiChu(Product $product, array $row, ?ProductBlock $cu): ?ProductBlock
    {
        $chu = $this->locHtml->lamSach((string) ($row['body'] ?? ''));

        // Khối chữ rỗng là khối người dùng để trống — bỏ, và xoá bản cũ nếu có.
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

    /**
     * @param  list<string>  $stored
     */
    private function khoiAnh(Product $product, array $row, ?ProductBlock $cu, array &$stored): ?ProductBlock
    {
        $file = $row['image'] ?? null;
        $caption = trim((string) ($row['caption'] ?? '')) ?: null;

        $duong = $cu?->image_path;

        if ($file instanceof UploadedFile) {
            // Ảnh mới thay ảnh cũ: lưu trước, xoá sau — hỏng giữa chừng thì
            // khối vẫn còn một ảnh dùng được.
            $moi = $this->anh->luu($file, 'products/blocks');
            $stored[] = $moi;

            if ($duong) {
                $this->anh->xoa($duong);
            }

            $duong = $moi;
        }

        // Khối ảnh mà không có ảnh nào thì không phải một khối.
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
