<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = ['cart_id', 'product_id', 'product_variant_id', 'quantity'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_selected' => 'boolean',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function unitPrice(): string
    {
        if ($this->variant && $this->variant->price !== null) {
            return (string) $this->variant->price;
        }

        return $this->product->price()->finalPrice;
    }

    public function lineTotal(): string
    {
        return bcmul($this->unitPrice(), (string) $this->quantity, 2);
    }

    public function availableStock(): ?int
    {
        if ($this->variant) {
            return $this->variant->track_inventory ? (int) $this->variant->stock_quantity : null;
        }

        return $this->product->track_inventory ? (int) $this->product->stock_quantity : null;
    }
}
