<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một thông báo trong trang. */
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function boardingBooking(): BelongsTo
    {
        return $this->belongsTo(BoardingBooking::class);
    }

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

    public function duongDan(): string
    {
        if ($this->type === NotificationType::TinNhan) {
            return route('shop.chat.index');
        }

        if ($this->type === NotificationType::ChamHo) {
            return $this->boardingBooking
                ? route('shop.boarding.show', $this->boardingBooking)
                : route('shop.boarding.mine');
        }

        if ($this->type === NotificationType::HangVe) {
            $sp = $this->product;

            return $sp ? route('shop.products.show', $sp) : route('shop.products.index');
        }

        if (! $this->community_post_id) {
            return route('shop.community.index');
        }

        $bai = $this->post;

        if (! $bai || ! $bai->isApproved() || $bai->isHidden()) {
            return route('shop.community.index', ['tab' => 'cua-toi']);
        }

        return route('shop.community.show', $this->community_post_id)
            . ($this->community_comment_id ? '#binh-luan-' . $this->community_comment_id : '');
    }
}
