<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Một hạng thành viên. */
class MemberTier extends Model
{
    protected $fillable = [
        'name',
        'min_spend',
        'discount_percent',
        'free_shipping_from',
        'bonus_points_percent',
    ];

    protected function casts(): array
    {
        return [
            'min_spend' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'free_shipping_from' => 'decimal:2',
            'bonus_points_percent' => 'integer',
        ];
    }
}
