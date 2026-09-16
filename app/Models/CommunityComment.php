<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bình luận dưới một bài Góc cây.
 *
 * MỘT TẦNG TRẢ LỜI như Facebook: bình luận gốc (`parent_id` null) và các câu
 * trả lời thụt vào bên dưới. Trả lời một câu trả lời thì vẫn gắn vào bình luận
 * gốc, kèm `reply_to_user_id` để hiện "trả lời Tên" — biết ai đang nói với ai
 * mà không thụt lề tới hết màn hình điện thoại.
 *
 * `user_id`, `community_post_id`, `parent_id`, `hidden_at` KHÔNG nằm trong
 * $fillable: người viết, bài và chỗ trả lời do hệ thống đặt sau khi kiểm.
 */
class CommunityComment extends Model
{
    protected $fillable = ['body'];

    protected function casts(): array
    {
        return [
            'hidden_at' => 'datetime',
            'edited_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'community_post_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Câu trả lời, cũ trước mới sau — đọc như một cuộc hội thoại. */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at')->orderBy('id');
    }

    /** Người mà câu trả lời này nhắm tới (khi trả lời một câu trả lời). */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reply_to_user_id');
    }

    /** Bình luận hiện ra ngoài: chưa bị cửa hàng ẩn. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('hidden_at');
    }

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }
}
