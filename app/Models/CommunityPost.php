<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một bài trong "Góc cây của bạn".
 * ⚠️ NỘI DUNG CÔNG KHAI DO NGƯỜI LẠ ĐĂNG. Hai luật không được nới:
 */
class CommunityPost extends Model
{
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

    public function media(): HasMany
    {
        return $this->hasMany(CommunityPostMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    public function likers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_post_likes');
    }

    public function savers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_post_saves');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CommunityComment::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereNotNull('approved_at')->whereNull('hidden_at')->whereNull('author_hidden_at');
    }

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

    public function anhDau(): ?CommunityPostMedia
    {
        return $this->media->first(fn (CommunityPostMedia $m) => ! $m->laVideo());
    }

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
