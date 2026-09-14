<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một bài trong "Góc cây của bạn".
 * ============================================================
 * ⚠️ NỘI DUNG CÔNG KHAI DO NGƯỜI LẠ ĐĂNG. Hai luật không được nới:
 *
 *   1. Chỉ bài ĐÃ DUYỆT mới hiện ra ngoài (`scopeApproved`).
 *   2. Ảnh phải đi qua `ImageStore::luu()` để tước metadata — ảnh chụp
 *      bằng điện thoại mang theo toạ độ GPS nhà người chụp.
 */
class CommunityPost extends Model
{
    /*
     * `user_id`, `approved_at`, `rejected_at` CỐ Ý không nằm ở đây.
     *
     * Chủ sở hữu và trạng thái duyệt là thứ hệ thống đặt, không phải thứ
     * nhận từ biểu mẫu. Cho vào $fillable thì ai gửi thêm một trường
     * `approved_at` là bài của họ tự lên trang.
     */
    protected $fillable = ['body', 'photo', 'product_id'];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
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

    /** Những người đã thích bài. Đếm bằng withCount('likers'). */
    public function likers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_post_likes');
    }

    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CommunityComment::class);
    }

    /** CỬA DUY NHẤT để lấy bài hiện ra ngoài. */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereNotNull('approved_at');
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

    /**
     * Trạng thái cho chính người đăng đọc.
     *
     * Người gửi bài phải biết bài mình đang ở đâu. Im lặng thì họ gửi
     * lại y hệt, rồi lại chờ, rồi kết luận là trang hỏng.
     */
    public function statusText(): string
    {
        return match (true) {
            $this->isApproved() => 'Đã đăng',
            $this->isRejected() => 'Không được duyệt',
            default => 'Đang chờ duyệt',
        };
    }

    public function statusBadge(): string
    {
        return match (true) {
            $this->isApproved() => 'success',
            $this->isRejected() => 'danger',
            default => 'warning',
        };
    }
}
