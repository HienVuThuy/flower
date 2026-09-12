<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một mục trong thư viện sản phẩm: ẢNH hoặc VIDEO.
 *
 * Tên model giữ nguyên theo tên bảng — xem chú thích ở migration
 * add_video_to_product_images_table để biết vì sao không đổi tên.
 *
 * BA DẠNG, phân biệt bằng `kind` và cột nào có giá trị:
 *
 *   kind=image, path=...            ảnh
 *   kind=video, path=...            tệp MP4 cửa hàng tự giữ
 *   kind=video, video_url=...       link nhúng YouTube/Vimeo đã chuẩn hoá
 */
class ProductImage extends Model
{
    public const ANH = 'image';

    public const VIDEO = 'video';

    protected $fillable = ['kind', 'path', 'video_url', 'alt', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    protected $attributes = ['kind' => self::ANH];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function laVideo(): bool
    {
        return $this->kind === self::VIDEO;
    }

    /** Video dạng link nhúng (YouTube/Vimeo) — null nếu là tệp hoặc là ảnh. */
    public function linkNhung(): ?string
    {
        return $this->laVideo() ? $this->video_url : null;
    }

    /**
     * Link XEM TRÊN TRANG GỐC, dựng lại từ địa chỉ nhúng.
     *
     * Cần cho đường không-JavaScript: khối video chỉ nạp trình phát khi khách
     * bấm, nên không có JS thì phải còn một liên kết bấm được, không phải một ô
     * trống. Dựng lại từ mã video chứ không lưu thêm một cột — hai cột cùng nói
     * về một video là hai cột có thể lệch nhau.
     */
    public function linkXem(): ?string
    {
        $nhung = $this->linkNhung();

        if ($nhung === null) {
            return null;
        }

        if (str_contains($nhung, 'youtube-nocookie.com/embed/')) {
            return 'https://www.youtube.com/watch?v=' . basename(parse_url($nhung, PHP_URL_PATH) ?: '');
        }

        if (str_contains($nhung, 'player.vimeo.com/video/')) {
            return 'https://vimeo.com/' . basename(parse_url($nhung, PHP_URL_PATH) ?: '');
        }

        return null;
    }

    /** Video dạng tệp MP4 trên đĩa — null nếu là link hoặc là ảnh. */
    public function tepVideo(): ?string
    {
        return $this->laVideo() && $this->video_url === null ? $this->path : null;
    }
}
