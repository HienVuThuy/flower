<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Một ảnh trong thư viện của bài Cẩm nang — admin chèn vào thân bài bằng đường dẫn này. */
class BlogPostImage extends Model
{
    protected $fillable = ['path', 'alt', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    /** Đường dẫn dùng trong thẻ ảnh của thân bài (cùng tên miền). */
    public function duongDan(): string
    {
        return Storage::url($this->path);
    }

    /** Đoạn mã admin chép vào thân bài. */
    public function maChen(): string
    {
        $alt = trim((string) $this->alt);

        return '<figure><img src="'.e($this->duongDan()).'" alt="'.e($alt).'">'
            .($alt !== '' ? '<figcaption>'.e($alt).'</figcaption>' : '')
            .'</figure>';
    }
}
