<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một mục trong thư viện sản phẩm: ẢNH hoặc VIDEO. */
class ProductImage extends Model
{
    public const ANH = 'image';

    public const VIDEO = 'video';

    protected $fillable = ['kind', 'path', 'video_url', 'alt', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    protected $attributes = ['kind' => self::ANH];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function laVideo(): bool
    {
        return $this->kind === self::VIDEO;
    }

    public function linkNhung(): ?string
    {
        return $this->laVideo() ? $this->video_url : null;
    }

    public function linkXem(): ?string
    {
        $nhung = $this->linkNhung();

        if ($nhung === null) {
            return null;
        }

        if (str_contains($nhung, 'youtube-nocookie.com/embed/')) {
            return 'https://www.youtube.com/watch?v=' . basename(parse_url($nhung, PHP_URL_PATH) ?: '');
        }

        if (str_contains($nhung, 'player.vimeo.com/video/')) {
            return 'https://vimeo.com/' . basename(parse_url($nhung, PHP_URL_PATH) ?: '');
        }

        return null;
    }

    public function tepVideo(): ?string
    {
        return $this->laVideo() && $this->video_url === null ? $this->path : null;
    }
}
