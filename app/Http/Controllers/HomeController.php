<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\FlowerCollections;
use App\Services\Recommendation\RecommendationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        private readonly RecommendationService $recommendations,
        private readonly FlowerCollections $boSuuTap,
    ) {
    }

    public function index(Request $request): View
    {
        $categories = Category::query()
            ->plants()
            ->where('is_active', true)
            ->whereHas('products', fn ($q) => $q->whereIn('status', ['active', 'out_of_stock']))
            ->withCount([
                'products' => fn ($q) => $q->whereIn('status', ['active', 'out_of_stock']),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->take(4)
            ->get();

        $featuredProducts = Product::query()
            ->with(['category', 'promotions',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->mainCatalog()
            ->whereIn('status', ['active', 'out_of_stock'])
            ->newArrivals()
            ->take(\App\Services\Shop\ThamSoKinhDoanh::so('catalog.new_arrival_limit'))
            ->get();

        $noiBat = Product::query()
            ->with(['category', 'promotions',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->mainCatalog()
            ->where('is_featured', true)
            ->whereIn('status', ['active', 'out_of_stock'])
            ->latest()
            ->take(8)
            ->get();

        $reco = $this->recommendations->forViewer(
            $request->user()?->id,
            $request->session()->getId(),
        );

        $theoDip = $this->boSuuTap->soLuongTheoDip();

        return view('welcome', [
            'categories' => $categories,
            'theoDip' => $theoDip,
            'dipSapToi' => $this->boSuuTap->dipSapToi($theoDip),
            'soLuongBoSuuTap' => $this->boSuuTap->soLuong(),
            'featuredProducts' => $featuredProducts,
            'noiBat' => $noiBat,
            'mostWished' => $this->mostWished($reco['items']->pluck('product.id')->all()),
            'recommendations' => $reco['items'],
            'recommendationsArePersonal' => $reco['personalized'],
        ]);
    }

    private function mostWished(array $excludeIds = []): \Illuminate\Support\Collection
    {
        return Product::query()
            ->with(['category', 'promotions',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->withCount('wishlists')
            ->mainCatalog()
            ->whereIn('status', ['active', 'out_of_stock'])
            ->whereHas('wishlists')
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->addSelect(['last_wished_at' => \App\Models\Wishlist::query()
                ->selectRaw('MAX(created_at)')
                ->whereColumn('wishlists.product_id', 'products.id'),
            ])
            ->orderByDesc('last_wished_at')
            ->take(4)
            ->get();
    }
}
