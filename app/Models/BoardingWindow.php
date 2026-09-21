<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Đợt trả cây theo dịp — ví dụ "Tết 2027: mang cây về ngày 28/01, nhận lại ngày 20/02". */
class BoardingWindow extends Model
{
    protected $fillable = ['group_key', 'name', 'return_on', 'take_back_on', 'is_active'];

    protected function casts(): array
    {
        return [
            'return_on' => 'date',
            'take_back_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function scopeSapToi(Builder $q, \DateTimeInterface $tu): Builder
    {
        return $q->where('is_active', true)->whereDate('return_on', '>', $tu)->orderBy('return_on');
    }
}
