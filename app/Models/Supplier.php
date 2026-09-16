<?php

namespace App\Models;

use App\Enums\SupplierKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Một nơi cửa hàng lấy hàng. */
class Supplier extends Model
{
    protected $fillable = [
        'name',
        'kind',
        'phone',
        'email',
        'address',
        'note',
        'is_active',
    ];

    protected $attributes = [
        'kind' => 'khac',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'kind' => SupplierKind::class,
            'is_active' => 'boolean',
        ];
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(StockReceipt::class);
    }

    public function flowerLots(): HasMany
    {
        return $this->hasMany(FlowerLot::class);
    }

    public function scopeDangHoatDong(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
