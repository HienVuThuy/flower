<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một ảnh hoặc video của bài Góc cây. */
class CommunityPostMedia extends Model
{
    protected $table = 'community_post_media';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'community_post_id');
    }

    public function laVideo(): bool
    {
        return $this->kind === 'video';
    }

    public function url(): string
    {
        return asset('storage/' . $this->path);
    }
}
