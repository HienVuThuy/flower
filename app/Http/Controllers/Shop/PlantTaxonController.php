<?php

namespace App\Http\Controllers\Shop;

use App\Enums\TaxonRank;
use App\Http\Controllers\Controller;
use App\Models\PlantTaxon;
use App\Models\Product;
use Illuminate\View\View;

/** Duyệt cây theo phân loại sinh học. */
class PlantTaxonController extends Controller
{
    public function index(): View
    {
        $goc = PlantTaxon::roots()->with('children')->get();

        $ho = PlantTaxon::rank(TaxonRank::Family)
            ->orderBy('name')
            ->get()
            ->map(fn (PlantTaxon $t) => [
                'taxon' => $t,
                'soSanPham' => $this->demSanPham($t),
            ])
            ->filter(fn (array $r) => $r['soSanPham'] > 0)
            ->sortByDesc('soSanPham')
            ->values();

        return view('shop.taxa.index', [
            'goc' => $goc,
            'ho' => $ho,
        ]);
    }

    public function show(PlantTaxon $taxon): View
    {
        $products = Product::query()
            ->with(['category', 'promotions',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->mainCatalog()
            ->whereIn('status', ['active', 'out_of_stock'])
            ->inTaxon($taxon)
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $nhanhCon = $taxon->children->map(fn (PlantTaxon $con) => [
            'taxon' => $con,
            'soSanPham' => $this->demSanPham($con),
        ]);

        return view('shop.taxa.show', [
            'taxon' => $taxon,
            'chain' => $taxon->chain(),
            'nhanhCon' => $nhanhCon,
            'products' => $products,
        ]);
    }

    private function demSanPham(PlantTaxon $taxon): int
    {
        return Product::query()
            ->mainCatalog()
            ->whereIn('status', ['active', 'out_of_stock'])
            ->inTaxon($taxon)
            ->count();
    }
}
