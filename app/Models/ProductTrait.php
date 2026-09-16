<?php

namespace App\Models;

use App\Enums\TraitType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một nhãn phân loại gắn với một sản phẩm. */
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

    public function label(): string
    {
        return $this->trait_type->labelFor($this->trait_value);
    }

    public static function isValid(TraitType $type, string $value): bool
    {
        return array_key_exists($value, $type->options());
    }
}
