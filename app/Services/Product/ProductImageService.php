<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Media\ImageStore;
use App\Services\Media\VideoLink;
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
     * Thêm VIDEO vào cuối thư viện: link YouTube/Vimeo và/hoặc tệp MP4.
     *
     * LINK ĐƯỢC DỰNG LẠI, KHÔNG LƯU NGUYÊN CHUỖI NGƯỜI DÙNG DÁN. VideoLink chỉ
     * lấy mã video rồi tự dựng địa chỉ nhúng; chuỗi nào không ra mã thì bỏ qua
     * ở đây luôn, không tin rằng tầng kiểm tra biểu mẫu đã lọc hết.
     *
     * TỆP MP4 KHÔNG ĐI QUA ImageStore: lớp đó tước metadata ảnh và sinh bản
     * WebP — cả hai đều vô nghĩa với video, và `toiUu()` gọi lên một tệp không
     * phải ảnh chỉ tổ ghi log lỗi.
     *
     * @param  array<int, string|null>  $links
     * @param  array<int, UploadedFile|null>  $files
     * @return list<string> đường dẫn tệp vừa lưu, để rollback nếu transaction hỏng
     */
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

    /**
     * Xoá các mục thư viện (ảnh HOẶC video) theo id.
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

        // media() chứ không images(): admin phải xoá được cả video.
        $items = $product->media()->whereIn('id', $imageIds)->get();

        foreach ($items as $item) {
            // Video dạng link không có tệp nào trên đĩa để xoá.
            if ($item->path) {
                $this->anh->xoa($item->path);
            }

            $item->delete();
        }
    }

    /** Xoá toàn bộ tệp thư viện (ảnh và video) khi sản phẩm bị xoá hẳn. */
    public function purge(Product $product): void
    {
        foreach ($product->media as $item) {
            if ($item->path) {
                $this->anh->xoa($item->path);
            }
        }
    }

    /** Dọn file đã upload khi transaction thất bại. */
    public function rollback(array $paths): void
    {
        foreach ($paths as $path) {
            $this->anh->xoa($path);
        }
    }
}
