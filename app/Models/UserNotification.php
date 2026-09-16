<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một thông báo trong trang. Chỉ NotificationCenter ghi.
 */
class UserNotification extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Người gây ra việc này; null khi là cửa hàng. */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'community_post_id');
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(CommunityComment::class, 'community_comment_id');
    }

    public function scopeChuaDoc(Builder $q): Builder
    {
        return $q->whereNull('read_at');
    }

    public function daDoc(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Chỗ cần đưa người nhận tới khi họ bấm vào thông báo.
     *
     * Bài đã bị xoá thì về Góc cây chứ không 404: thông báo cũ vẫn đọc được,
     * chỉ là chỗ nó trỏ tới không còn.
     */
    public function duongDan(): string
    {
        if (! $this->community_post_id) {
            return route('shop.community.index');
        }

        // Bài chưa duyệt / bị ẩn không mở được trang công khai — về mục "Bài của tôi".
        $bai = $this->post;

        if (! $bai || ! $bai->isApproved() || $bai->isHidden()) {
            return route('shop.community.index', ['tab' => 'cua-toi']);
        }

        return route('shop.community.show', $this->community_post_id)
            . ($this->community_comment_id ? '#binh-luan-' . $this->community_comment_id : '');
    }
}
