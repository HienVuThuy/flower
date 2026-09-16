<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một bài trong "Góc cây của bạn".
 * ============================================================
 * ⚠️ NỘI DUNG CÔNG KHAI DO NGƯỜI LẠ ĐĂNG. Hai luật không được nới:
 *
 *   1. Chỉ bài ĐÃ DUYỆT và KHÔNG BỊ ẨN mới hiện ra ngoài (`scopeApproved`).
 *   2. Ảnh / video đi qua CommunityMediaStore: ảnh bị tước metadata, video bị
 *      xoá toạ độ GPS — tệp quay bằng điện thoại mang theo vị trí nhà người quay.
 */
class CommunityPost extends Model
{
    /*
     * `user_id`, `approved_at`, `rejected_at`, `hidden_at` CỐ Ý không nằm ở đây:
     * chủ sở hữu và trạng thái duyệt / ẩn do hệ thống đặt, không nhận từ biểu mẫu.
     */
    protected $fillable = ['body', 'product_id'];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'hidden_at' => 'datetime',
            'author_hidden_at' => 'datetime',
            'comments_locked_at' => 'datetime',
            'pinned_at' => 'datetime',
            'edited_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Ảnh và video của bài, đúng thứ tự người đăng chọn. */
    public function media(): HasMany
    {
        return $this->hasMany(CommunityPostMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Những người đã thích bài. Đếm bằng withCount('likers'). */
    public function likers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_post_likes');
    }

    /** Những người đã lưu bài. */
    public function savers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_post_saves');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CommunityComment::class);
    }

    /**
     * CỬA DUY NHẤT để lấy bài hiện ra ngoài: đã duyệt, không bị cửa hàng ẩn, và
     * chính chủ cũng không tạm ẩn.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereNotNull('approved_at')->whereNull('hidden_at')->whereNull('author_hidden_at');
    }

    /**
     * Bài do CHÍNH CHỦ tạm ẩn — vẫn đã duyệt, cửa hàng không ẩn.
     *
     * Dùng để chủ bài mở lại được bài mình vừa ẩn: `approved()` đã loại nó ra
     * khỏi mọi đường công khai, không có ngoại lệ này thì chính chủ bấm vào bài
     * của mình cũng nhận 404 và không còn chỗ nào bật lại.
     */
    public function scopeAuthorHidden(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId)
            ->whereNotNull('approved_at')
            ->whereNull('hidden_at')
            ->whereNotNull('author_hidden_at');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('approved_at')->whereNull('rejected_at');
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function isRejected(): bool
    {
        return $this->rejected_at !== null;
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    /** Chính chủ tạm ẩn bài của mình (khác với cửa hàng ẩn vì vi phạm). */
    public function tuAn(): bool
    {
        return $this->author_hidden_at !== null;
    }

    public function khoaBinhLuan(): bool
    {
        return $this->comments_locked_at !== null;
    }

    public function daGhim(): bool
    {
        return $this->pinned_at !== null;
    }

    /** Ảnh đầu tiên (cho thẻ nhỏ ở trang sản phẩm, ảnh chia sẻ). */
    public function anhDau(): ?CommunityPostMedia
    {
        return $this->media->first(fn (CommunityPostMedia $m) => ! $m->laVideo());
    }

    /**
     * Trạng thái cho chính người đăng đọc.
     *
     * Người gửi bài phải biết bài mình đang ở đâu. Im lặng thì họ gửi
     * lại y hệt, rồi lại chờ, rồi kết luận là trang hỏng.
     */
    public function statusText(): string
    {
        return match (true) {
            $this->isHidden() => 'Bị ẩn',
            $this->tuAn() => 'Bạn đang ẩn',
            $this->isApproved() => 'Đã đăng',
            $this->isRejected() => 'Không được duyệt',
            default => 'Đang chờ duyệt',
        };
    }

    public function statusBadge(): string
    {
        return match (true) {
            $this->isHidden() => 'danger',
            $this->tuAn() => 'secondary',
            $this->isApproved() => 'success',
            $this->isRejected() => 'danger',
            default => 'warning',
        };
    }
}
