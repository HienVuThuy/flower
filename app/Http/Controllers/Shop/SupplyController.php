<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\Recommendation\RecommendationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Trang "Phụ kiện & vật tư chăm sóc". */
class SupplyController extends Controller
{
    public function __construct(
        private readonly RecommendationService $recommendations,
    ) {
    }

    public function index(Request $request): View
    {
        $query = Product::query()
            ->with(['category', 'promotions',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->supplyCatalog()
            ->whereIn('status', ['active', 'out_of_stock']);

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->string('category')));
        }

        match ($request->string('sort')->toString()) {
            'price_desc' => $query->orderByEffectivePrice('desc'),
            'newest' => $query->latest(),
            default => $query->orderByEffectivePrice('asc'),
        };

        $products = $query->paginate(12)->withQueryString();

        return view('shop.supplies.index', [
            'products' => $products,
            'categories' => Category::query()
                ->supplies()
                ->where('is_active', true)
                ->withCount([
                    'products' => fn ($q) => $q->whereIn('status', ['active', 'out_of_stock']),
                ])
                ->orderBy('sort_order')
                ->get(),
        ]);
    }
}
