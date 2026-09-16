<?php

namespace App\Models;

use App\Enums\FlowerUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Một loại hoa thu mua — thứ người ta gọi tên khi ra chợ. */
class FlowerKind extends Model
{
    protected $fillable = [
        'name',
        'default_unit',
        'note',
        'is_active',
    ];

    protected $attributes = [
        'default_unit' => 'bo',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'default_unit' => FlowerUnit::class,
            'is_active' => 'boolean',
        ];
    }

    public function lots(): HasMany
    {
        return $this->hasMany(FlowerLot::class);
    }

    public function scopeDangDung(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
