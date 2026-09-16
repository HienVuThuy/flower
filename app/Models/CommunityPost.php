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

    /** CỬA DUY NHẤT để lấy bài hiện ra ngoài: đã duyệt và không bị cửa hàng ẩn. */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereNotNull('approved_at')->whereNull('hidden_at');
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
            $this->isApproved() => 'Đã đăng',
            $this->isRejected() => 'Không được duyệt',
            default => 'Đang chờ duyệt',
        };
    }

    public function statusBadge(): string
    {
        return match (true) {
            $this->isHidden() => 'danger',
            $this->isApproved() => 'success',
            $this->isRejected() => 'danger',
            default => 'warning',
        };
    }
}
