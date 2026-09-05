<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một dòng trong danh sách yêu thích.
 *
 * Cố ý mỏng: chỉ nối user với product. Mọi câu hỏi thú vị ("khách này
 * thích gì", "sản phẩm này được bao nhiêu người thích") đều trả lời được
 * bằng quan hệ, không cần thêm cột.
 */
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

    /**
     * Id các sản phẩm người này đã thích — ĐỌC MỘT LẦN cho cả request.
     *
     * Trang danh sách có 12–24 card, mỗi card cần biết mình đã được thích
     * chưa. Hỏi cơ sở dữ liệu từng card là 24 truy vấn cho một thông tin
     * bé xíu. Nhớ lại trong biến tĩnh: đúng 1 truy vấn, và biến tĩnh chỉ
     * sống trong một request nên không có chuyện dữ liệu cũ dính sang
     * người dùng khác.
     *
     * @return array<int, int>
     */
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
