<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Services\Media\ImageStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Quản lý thư viện ảnh phụ của sản phẩm.
 *
 * Tách khỏi controller vì logic upload/xoá file có tác dụng phụ ra
 * ngoài database (ghi vào disk) và cần được gọi lại giống hệt nhau
 * ở cả store() lẫn update().
 */
class ProductImageService
{
    public function __construct(private readonly ImageStore $anh)
    {
    }

    /**
     * Thêm ảnh phụ vào cuối thư viện.
     *
     * @param  array<UploadedFile>  $files
     * @return list<string>  đường dẫn các file vừa lưu, để rollback nếu transaction hỏng
     */
    public function attach(Product $product, array $files): array
    {
        $stored = [];

        $nextOrder = (int) $product->images()->max('sort_order') + 1;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            /*
             * Qua ImageStore để ảnh phụ cũng có bản WebP.
             *
             * Ảnh phụ trước đây bị bỏ sót ở CẢ HAI đường: không tối ưu
             * lúc tải lên, và lệnh `anh:toi-uu` thì quét bằng files()
             * không đệ quy nên không nhìn thấy thư mục con `gallery`.
             */
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

    /**
     * Xoá các ảnh phụ theo id.
     *
     * Chỉ xoá ảnh THUỘC sản phẩm này — chặn việc gửi id ảnh của sản
     * phẩm khác lên để xoá trộm.
     *
     * @param  array<int|string>  $imageIds
     */
    public function detach(Product $product, array $imageIds): void
    {
        if (! $imageIds) {
            return;
        }

        $images = $product->images()->whereIn('id', $imageIds)->get();

        foreach ($images as $image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }
    }

    /** Xoá toàn bộ file ảnh phụ khi sản phẩm bị xoá hẳn. */
    public function purge(Product $product): void
    {
        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->path);
        }
    }

    /** Dọn file đã upload khi transaction thất bại. */
    public function rollback(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk('public')->delete($path);
        }
    }
}
