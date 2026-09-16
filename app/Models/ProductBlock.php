<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một khối trong phần mô tả chi tiết: một đoạn chữ, hoặc một ảnh kèm chú. */
class ProductBlock extends Model
{
    public const CHU = 'text';

    public const ANH = 'image';

    protected $fillable = ['kind', 'body', 'image_path', 'caption', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function laAnh(): bool
    {
        return $this->kind === self::ANH;
    }
}
