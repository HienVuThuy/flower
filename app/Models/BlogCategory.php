<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Chuyên mục của Cẩm nang.
 *
 * Cố ý ÍT chuyên mục và mỗi cái rộng: "Chăm cây", "Chọn cây", "Ý nghĩa
 * hoa", "Trang trí". Chia nhỏ hơn thì mỗi chuyên mục chỉ có hai ba bài
 * và trang danh sách trông như bỏ hoang.
 */
class BlogCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }
}
