<?php

namespace App\Models;

use App\Enums\TraitType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một nhãn phân loại gắn với một sản phẩm.
 *
 * Xem App\Enums\TraitType để biết vì sao ba loại nhãn dùng chung một
 * bảng, và điều kiện để cách đó không biến bảng thành thùng rác.
 */
class ProductTrait extends Model
{
    protected $fillable = ['product_id', 'trait_type', 'trait_value'];

    protected function casts(): array
    {
        return ['trait_type' => TraitType::class];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Nhãn tiếng Việt của giá trị này. */
    public function label(): string
    {
        return $this->trait_type->labelFor($this->trait_value);
    }

    /**
     * Giá trị này có hợp lệ với loại nhãn đó không.
     *
     * ĐÂY LÀ RÀNG BUỘC GIỮ CHO BẢNG KHÔNG THÀNH THÙNG RÁC.
     * Bảng dùng varchar nên cơ sở dữ liệu không chặn được giá trị lạ;
     * chặn phải nằm ở đây, và mọi đường ghi đều đi qua
     * Product::syncTraits().
     */
    public static function isValid(TraitType $type, string $value): bool
    {
        return array_key_exists($value, $type->options());
    }
}
