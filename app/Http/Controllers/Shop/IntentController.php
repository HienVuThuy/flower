<?php

namespace App\Http\Controllers\Shop;

use App\Enums\CareDifficulty;
use App\Enums\Placement;
use App\Enums\SellingForm;
use App\Enums\ShoppingIntent;
use App\Enums\TraitType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Recommendation\PlantAdvisor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** Trang hướng dẫn theo NHU CẦU ("Chọn theo nhu cầu" ở trang chủ). */
class IntentController extends Controller
{
    public function __construct(
        private readonly PlantAdvisor $advisor,
    ) {
    }

    public function show(string $intent): View
    {
        $case = ShoppingIntent::tryFrom($intent);

        abort_if($case === null, 404);

        return view('shop.intents.show', [
            'intent' => $case,
            'products' => $this->productsFor($case),
            'extras' => $this->extrasFor($case),
        ]);
    }

    private function productsFor(ShoppingIntent $intent): Collection
    {
        return match ($intent) {
            ShoppingIntent::Gift => $this->base()
                ->whereIn('selling_form', [
                    SellingForm::Bouquet->value,
                    SellingForm::Box->value,
                    SellingForm::Basket->value,
                    SellingForm::Set->value,
                ])
                ->orderByEffectivePrice('asc')
                ->take(8)
                ->get(),

            ShoppingIntent::Decor => $this->base()
                ->whereHas('traits', fn ($q) => $q
                    ->where('trait_type', TraitType::Placement->value)
                    ->whereIn('trait_value', [
                        Placement::LivingRoom->value,
                        Placement::Bedroom->value,
                        Placement::Desk->value,
                        Placement::WindowSill->value,
                        Placement::Hallway->value,
                    ]))
                ->take(8)
                ->get(),

            ShoppingIntent::Event => $this->base()
                ->where(fn (Builder $q) => $q
                    ->whereIn('selling_form', [
                        SellingForm::Arrangement->value,
                        SellingForm::Basket->value,
                    ])
                    ->orWhereHas('category', fn ($c) => $c->where('slug', 'hoa-khai-truong-su-kien')))
                ->take(8)
                ->get(),

            ShoppingIntent::Beginner => $this->advisor->suggest(
                difficulty: CareDifficulty::Easy,
                limit: 8,
            ),
        };
    }

    private function extrasFor(ShoppingIntent $intent): Collection
    {
        $forms = match ($intent) {
            ShoppingIntent::Beginner, ShoppingIntent::Decor => ['pot', 'original', 'set'],
            ShoppingIntent::Gift, ShoppingIntent::Event => ['bouquet', 'basket', 'box', 'arrangement'],
        };

        return Product::query()
            ->with(['category', 'promotions',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->supplyCatalog()
            ->where('status', 'active')
            ->whereHas('traits', fn ($q) => $q
                ->where('trait_type', TraitType::AccessoryFor->value)
                ->whereIn('trait_value', array_merge($forms, ['all'])))
            ->orderByEffectivePrice('asc')
            ->take(4)
            ->get();
    }

    private function base(): Builder
    {
        return Product::query()
            ->with(['category', 'promotions',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->mainCatalog()
            ->where('status', 'active');
    }
}
