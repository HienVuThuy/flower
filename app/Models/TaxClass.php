<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Một nhóm thuế suất mà sản phẩm được gán vào. */
class TaxClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'rate',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:5',
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function isExempt(): bool
    {
        return $this->rate === null;
    }

    public function rateString(): ?string
    {
        return $this->rate === null ? null : (string) $this->rate;
    }

    public function rateLabel(): string
    {
        if ($this->isExempt()) {
            return 'Không chịu VAT';
        }

        $phanTram = rtrim(rtrim(number_format((float) $this->rate * 100, 3, '.', ''), '0'), '.');

        return $phanTram . '%';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
