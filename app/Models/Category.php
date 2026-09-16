<?php

namespace App\Models;

use App\Enums\CategoryKind;
use App\Observers\CategoryObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([CategoryObserver::class])]
class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'kind',
        'description',
        'image',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'kind' => CategoryKind::class,
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopePlants(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q
            ->where('kind', CategoryKind::Plant->value)
            ->orWhereNull('kind'));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSupplies(Builder $query): Builder
    {
        return $query->where('kind', CategoryKind::Supply->value);
    }

    public function isSupply(): bool
    {
        return $this->kind === CategoryKind::Supply;
    }
}