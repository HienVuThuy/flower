<?php

namespace App\Models;

use App\Enums\TaxonRank;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** Một nút trong cây phân loại thực vật. */
class PlantTaxon extends Model
{
    protected $table = 'plant_taxa';

    protected $fillable = [
        'parent_id',
        'rank',
        'name',
        'scientific_name',
        'slug',
        'description',
        'image',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'rank' => TaxonRank::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'taxon_id');
    }

    public function chain(): Collection
    {
        $chuoi = collect([$this]);
        $nut = $this;

        $conLai = count(TaxonRank::cases());

        while ($nut->parent_id && $conLai-- > 0) {
            $nut = $nut->parent;

            if (! $nut) {
                break;
            }

            $chuoi->prepend($nut);
        }

        return $chuoi;
    }

    public function descendantIds(): array
    {
        $canh = self::query()->get(['id', 'parent_id'])->groupBy('parent_id');

        $ket = [$this->id];
        $hangDoi = [$this->id];

        while ($hangDoi) {
            $id = array_shift($hangDoi);

            foreach ($canh->get($id, collect()) as $con) {
                $ket[] = $con->id;
                $hangDoi[] = $con->id;
            }
        }

        return $ket;
    }

    public function displayName(): string
    {
        return $this->rank->label() . ' ' . $this->name;
    }

    public function fullName(): string
    {
        return $this->scientific_name
            ? sprintf('%s (%s)', $this->displayName(), $this->scientific_name)
            : $this->displayName();
    }

    public function scopeRank(Builder $query, TaxonRank $rank): Builder
    {
        return $query->where('rank', $rank);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }
}
