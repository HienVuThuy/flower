<?php

namespace App\Services\Recommendation;

use App\Enums\CareDifficulty;
use App\Enums\FengShuiElement;
use App\Enums\Placement;
use App\Enums\SellingForm;
use App\Enums\TraitType;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Tư vấn chọn cây theo NHU CẦU, không theo lượt xem. */
class PlantAdvisor
{
    private const LIMIT = 8;

    public function suggest(
        ?Placement $placement = null,
        ?FengShuiElement $element = null,
        ?CareDifficulty $difficulty = null,
        int $limit = self::LIMIT,
        array $traits = [],
    ): Collection {
        $query = $this->baseQuery();

        if ($placement) {
            $query->withTrait(TraitType::Placement, $placement->value);
        }

        if ($element) {
            $query->withTrait(TraitType::FengShui, $element->value);
        }

        if ($difficulty) {
            $this->onlyDifficulty($query, $difficulty);
        }

        foreach ($traits as $loai => $giaTri) {
            $type = TraitType::tryFrom((string) $loai);

            if ($type && $giaTri !== null && $giaTri !== '') {
                $query->withTrait($type, (string) $giaTri);
            }
        }

        return $query->limit($limit)->get();
    }

    public function availableTraitValues(TraitType $type): Collection
    {
        $counts = $this->traitCounts($type);

        return collect($type->options())
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
                'hint' => $this->hintFor($type, $value),
                'total' => (int) ($counts[$value] ?? 0),
            ])
            ->filter(fn (array $row) => $row['total'] > 0)
            ->values();
    }

    private function hintFor(TraitType $type, string $value): ?string
    {
        $enum = match ($type) {
            TraitType::Habitat => \App\Enums\Habitat::tryFrom($value),
            TraitType::GrowthForm => \App\Enums\GrowthForm::tryFrom($value),
            TraitType::Shape => \App\Enums\PlantShape::tryFrom($value),
            default => null,
        };

        return $enum && method_exists($enum, 'hint') ? $enum->hint() : null;
    }

    public function availableDifficulties(): Collection
    {
        return collect(CareDifficulty::cases())
            ->map(fn (CareDifficulty $d) => [
                'difficulty' => $d,
                'total' => $this->onlyDifficulty($this->baseQuery(), $d)->count(),
            ])
            ->filter(fn (array $row) => $row['total'] > 0)
            ->values();
    }

    public function accessoriesFor(Product $product, int $limit = 4): Collection
    {
        $form = $product->selling_form?->value;

        if ($form === null) {
            return collect();
        }

        return $this->baseQuery()
            ->where('id', '!=', $product->id)
            ->whereHas('traits', fn ($q) => $q
                ->where('trait_type', TraitType::AccessoryFor->value)
                ->whereIn('trait_value', [$form, 'all']))
            ->limit($limit)
            ->get();
    }

    public function accessoriesForBasket(iterable $products, int $limit = 4): Collection
    {
        $forms = collect($products)
            ->map(fn (Product $p) => $p->selling_form?->value)
            ->filter()
            ->unique()
            ->values();

        if ($forms->isEmpty()) {
            return collect();
        }

        $ids = collect($products)->pluck('id')->filter()->all();

        return $this->baseQuery()
            ->when($ids !== [], fn ($q) => $q->whereNotIn('id', $ids))
            ->whereHas('traits', fn ($q) => $q
                ->where('trait_type', TraitType::AccessoryFor->value)
                ->whereIn('trait_value', $forms->push('all')->all()))
            ->limit($limit)
            ->get();
    }

    public function forBeginners(int $limit = self::LIMIT): Collection
    {
        return $this->onlyDifficulty($this->baseQuery(), CareDifficulty::Easy)
            ->limit($limit)
            ->get();
    }

    public function availablePlacements(): Collection
    {
        $counts = $this->traitCounts(TraitType::Placement);

        return collect(Placement::cases())
            ->map(fn (Placement $p) => [
                'placement' => $p,
                'total' => (int) ($counts[$p->value] ?? 0),
            ])
            ->filter(fn (array $row) => $row['total'] > 0)
            ->values();
    }

    public function availableElements(): Collection
    {
        $counts = $this->traitCounts(TraitType::FengShui);

        return collect(FengShuiElement::cases())
            ->map(fn (FengShuiElement $e) => [
                'element' => $e,
                'total' => (int) ($counts[$e->value] ?? 0),
            ])
            ->filter(fn (array $row) => $row['total'] > 0)
            ->values();
    }

    private function traitCounts(TraitType $type): array
    {
        return \App\Models\ProductTrait::query()
            ->join('products', 'products.id', '=', 'product_traits.product_id')
            ->where('product_traits.trait_type', $type->value)
            ->where('products.status', 'active')
            ->whereNull('products.deleted_at')
            ->selectRaw('product_traits.trait_value, COUNT(*) as total')
            ->groupBy('product_traits.trait_value')
            ->pluck('total', 'trait_value')
            ->all();
    }

    private function baseQuery(): Builder
    {
        return Product::query()
            ->with(['category', 'promotions'])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->where('status', 'active')
            ->where(fn ($q) => $q
                ->where('track_inventory', false)
                ->orWhere('stock_quantity', '>', 0));
    }

    private function onlyDifficulty(Builder $query, CareDifficulty $difficulty): Builder
    {
        return $query->withCareDifficulty($difficulty);
    }
}
