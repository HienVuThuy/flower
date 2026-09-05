<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Một bài trong Cẩm nang.
 * ============================================================
 * `published_at` LÀ TRẠNG THÁI, không phải chỉ là ngày đăng.
 *
 *   - null            -> bản nháp, chưa ai ngoài admin thấy
 *   - ngày ở quá khứ  -> đang hiển thị
 *   - ngày ở tương lai -> đã lên lịch, tự hiện khi tới giờ
 *
 * Một cột trả lời ba câu. Thêm cột boolean `is_published` bên cạnh thì
 * có hai nguồn sự thật, và chúng sẽ lệch nhau đúng vào ngày ai đó sửa
 * một cột mà quên cột kia.
 */
class BlogPost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'blog_category_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'published_at',
        'meta_title',
        'meta_description',
    ];

    /*
     * `cover_image` và `author_id` KHÔNG nằm trong $fillable.
     *
     * `cover_image` là đường dẫn tệp do ImageStore sinh ra — cùng bài học
     * với `Journal::cover_image` (QĐ-153): để trong $fillable thì
     * `fill($validated)` gán đối tượng UploadedFile đè lên đường dẫn cũ
     * và tệp cũ không còn ai biết đường mà xoá.
     *
     * `author_id` là danh tính, không bao giờ nhận từ dữ liệu gửi lên.
     */

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Sản phẩm được nhắc trong bài.
     *
     * `note` là câu giải thích riêng cho lần nhắc này ("chịu bóng tốt
     * nhất trong danh sách"). Cùng một cây ở ba bài khác nhau thì mỗi bài
     * có lý do riêng để nhắc nó.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'blog_post_product')
            ->withPivot(['note', 'sort_order'])
            ->orderByPivot('sort_order');
    }

    /**
     * CỬA DUY NHẤT để lấy bài hiện ra ngoài.
     *
     * `<= now()` chứ không chỉ `whereNotNull`: bài đặt lịch đăng ngày mai
     * đã có `published_at` nhưng chưa được phép hiện. Bỏ vế so sánh thì
     * cả tính năng đặt lịch thành vô nghĩa mà không có gì báo.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }

    public function isScheduled(): bool
    {
        return $this->published_at !== null && $this->published_at->gt(now());
    }

    public function statusText(): string
    {
        return match (true) {
            $this->isScheduled() => 'Đã lên lịch ' . $this->published_at->format('d/m/Y H:i'),
            $this->isPublished() => 'Đang hiển thị',
            default => 'Bản nháp',
        };
    }

    public function statusBadge(): string
    {
        return match (true) {
            $this->isScheduled() => 'info',
            $this->isPublished() => 'success',
            default => 'secondary',
        };
    }

    /* ================= SEO ================= */

    /**
     * Tiêu đề cho thẻ `<title>` và cho Google.
     *
     * Để trống thì lấy tiêu đề bài. Bắt admin điền hai lần cho mọi bài là
     * cách chắc chắn để có những ô SEO bỏ trống — và một ô SEO trống thì
     * tệ hơn một mặc định hợp lý.
     */
    public function metaTitle(): string
    {
        return $this->meta_title ?: $this->title;
    }

    /**
     * Mô tả cho Google.
     *
     * Thứ tự lùi: ô SEO riêng -> đoạn tóm tắt -> cắt từ nội dung.
     *
     * Cắt từ nội dung là phương án CUỐI vì nó hay rơi vào giữa câu dẫn
     * nhập — đúng phần không nói gì về nội dung bài. Nhưng vẫn hơn để
     * trống: không có mô tả thì Google tự bịa một đoạn, và nó bịa tệ hơn.
     */
    public function metaDescription(): string
    {
        if ($this->meta_description) {
            return $this->meta_description;
        }

        if ($this->excerpt) {
            return $this->excerpt;
        }

        return Str::limit(trim(strip_tags($this->body)), 155);
    }

    /**
     * Thời gian đọc ước tính, tính bằng phút.
     *
     * 200 từ/phút là tốc độ đọc tiếng Việt thường được dùng. Con số này
     * là ƯỚC LƯỢNG và giao diện phải nói vậy ("khoảng 4 phút đọc") —
     * viết "4 phút đọc" trần là hứa một độ chính xác không có.
     *
     * Tối thiểu 1: "0 phút đọc" đọc ra như bài rỗng.
     */
    public function readingMinutes(): int
    {
        /*
         * ĐẾM THEO KHOẢNG TRẮNG, không dùng `str_word_count`.
         *
         * `str_word_count` được viết cho bảng chữ Latin không dấu. Với
         * tiếng Việt, mỗi ký tự có dấu nằm ngoài danh sách sẽ CẮT ĐÔI từ
         * — "tưới" thành hai từ, "khoảng" thành ba. Truyền thêm danh sách
         * ký tự cũng không đủ, vì tiếng Việt có 134 ký tự có dấu và
         * chúng là chuỗi nhiều byte.
         *
         * Tách theo khoảng trắng thì đúng với mọi bảng chữ, và với tiếng
         * Việt nó chính là đơn vị người ta đọc.
         */
        $chu = trim(preg_replace('/\s+/u', ' ', strip_tags($this->body)));
        $soTu = $chu === '' ? 0 : count(explode(' ', $chu));

        return max(1, (int) ceil($soTu / 200));
    }
}
