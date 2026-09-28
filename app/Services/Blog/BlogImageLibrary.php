<?php

namespace App\Services\Blog;

use App\Models\BlogPost;
use App\Models\BlogPostImage;
use App\Services\Media\ImageStore;
use Illuminate\Http\UploadedFile;

/**
 * Thư viện ảnh của bài Cẩm nang.
 *
 * Admin tải ảnh lên đây rồi chèn vào thân bài bằng đoạn mã có sẵn. Thân bài chỉ nhận
 * ảnh nằm trong kho của cửa hàng (xem HtmlSanitizer), nên mọi ảnh trong bài đều đi
 * qua bước tải lên này — không dán được ảnh của trang khác.
 */
class BlogImageLibrary
{
    private const THU_MUC = 'blog';

    public function __construct(
        private readonly ImageStore $anh,
    ) {
    }

    /**
     * @param  array<int, UploadedFile>  $tep
     * @return array<int, string> đường dẫn đã lưu (để hoàn lại nếu có lỗi)
     */
    public function them(BlogPost $post, array $tep): array
    {
        $daLuu = [];
        $thuTu = (int) $post->images()->max('sort_order');

        foreach ($tep as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $duong = $this->anh->luu($file, self::THU_MUC);
            $daLuu[] = $duong;

            $post->images()->create([
                'path' => $duong,
                'sort_order' => ++$thuTu,
            ]);
        }

        return $daLuu;
    }

    /** Sửa chú thích (cũng là chữ thay thế khi ảnh không hiện). */
    public function datChuThich(BlogPost $post, array $chuThich): void
    {
        foreach ($post->images()->get() as $anh) {
            if (! array_key_exists($anh->id, $chuThich)) {
                continue;
            }

            $moi = trim((string) $chuThich[$anh->id]) ?: null;

            if ($moi !== $anh->alt) {
                $anh->forceFill(['alt' => $moi])->save();
            }
        }
    }

    /** Xoá ảnh khỏi thư viện và xoá luôn tệp. */
    public function xoa(BlogPost $post, array $ids): int
    {
        $anhs = $post->images()->whereIn('id', $ids)->get();

        foreach ($anhs as $anh) {
            $this->anh->xoa($anh->path);
            $anh->delete();
        }

        return $anhs->count();
    }

    /** Xoá toàn bộ ảnh của bài (dùng khi xoá hẳn bài). */
    public function xoaTatCa(BlogPost $post): void
    {
        $this->xoa($post, $post->images()->pluck('id')->all());
    }

    public function hoanLai(array $duongDan): void
    {
        foreach ($duongDan as $duong) {
            $this->anh->xoa($duong);
        }
    }
}
