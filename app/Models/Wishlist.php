<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một dòng trong danh sách yêu thích. */
class Wishlist extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function productIdsFor(?int $userId): array
    {
        static $memo = [];

        if (! $userId) {
            return [];
        }

        return $memo[$userId] ??= self::where('user_id', $userId)
            ->pluck('product_id')
            ->all();
    }
}
