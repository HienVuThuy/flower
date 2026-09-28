<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** Một bài trong Cẩm nang. */
class BlogPost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'blog_category_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'published_at',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** Thư viện ảnh của bài — admin tải lên rồi chèn vào thân bài. */
    public function images(): HasMany
    {
        return $this->hasMany(BlogPostImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'blog_post_product')
            ->withPivot(['note', 'sort_order'])
            ->orderByPivot('sort_order');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }

    public function isScheduled(): bool
    {
        return $this->published_at !== null && $this->published_at->gt(now());
    }

    public function statusText(): string
    {
        return match (true) {
            $this->isScheduled() => 'Đã lên lịch ' . $this->published_at->format('d/m/Y H:i'),
            $this->isPublished() => 'Đang hiển thị',
            default => 'Bản nháp',
        };
    }

    public function statusBadge(): string
    {
        return match (true) {
            $this->isScheduled() => 'info',
            $this->isPublished() => 'success',
            default => 'secondary',
        };
    }

    public function metaTitle(): string
    {
        return $this->meta_title ?: $this->title;
    }

    public function metaDescription(): string
    {
        if ($this->meta_description) {
            return $this->meta_description;
        }

        if ($this->excerpt) {
            return $this->excerpt;
        }

        return Str::limit(trim(strip_tags($this->body)), 155);
    }

    public function readingMinutes(): int
    {
        $chu = trim(preg_replace('/\s+/u', ' ', strip_tags($this->body)));
        $soTu = $chu === '' ? 0 : count(explode(' ', $chu));

        return max(1, (int) ceil($soTu / 200));
    }
}
