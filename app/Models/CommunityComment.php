<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bình luận dưới một bài Góc cây.
 *
 * `user_id`, `community_post_id`, `hidden_at` KHÔNG nằm trong $fillable:
 * người viết và bài được bình luận do hệ thống đặt, trạng thái ẩn do cửa
 * hàng đặt.
 */
class CommunityComment extends Model
{
    protected $fillable = ['body'];

    protected function casts(): array
    {
        return ['hidden_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'community_post_id');
    }

    /** Bình luận hiện ra ngoài: chưa bị cửa hàng ẩn. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('hidden_at');
    }
}
