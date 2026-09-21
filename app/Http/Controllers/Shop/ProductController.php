<?php

namespace App\Http\Controllers\Shop;

use App\Enums\CareDifficulty;
use App\Enums\FlowerCollection;
use App\Services\Catalog\FlowerCollections;
use App\Services\Shop\Money;
use App\Enums\SellingForm;
use App\Enums\UserEventType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Models\ProductTrait;
use App\Models\PlantTaxon;
use App\Enums\TraitType;
use App\Models\Promotion;
use App\Models\Review;
use App\Models\UserEvent;
use App\Services\Recommendation\PlantAdvisor;
use App\Services\Recommendation\RecommendationService;
use App\Services\Search\ProductSearch;
use App\Services\Search\SearchTerms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductSearch $search,
        private readonly RecommendationService $recommendations,
        private readonly PlantAdvisor $advisor,
        private readonly FlowerCollections $boSuuTap,
    ) {
    }

    public static function sellingForms(): array
    {
        return SellingForm::options();
    }

    public function index(Request $request): View
    {
        $query = Product::query()
            ->with(['category', 'promotions',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->mainCatalog()
            ->whereIn('status', ['active', 'out_of_stock']);

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->string('category')));
        }

        if ($request->filled('selling_form')) {
            $query->where('selling_form', $request->string('selling_form'));
        }

        if ($request->filled('care_difficulty')) {
            $query->where('care_info->difficulty', $request->string('care_difficulty'));
        }

        $traitFilters = [];

        foreach (TraitType::filterable() as $type) {
            $value = (string) $request->query($type->queryKey(), '');

            if ($value === '' || ! array_key_exists($value, $type->options())) {
                continue;
            }

            $query->withTrait($type, $value);
            $traitFilters[$type->queryKey()] = $value;
        }

        $careDifficulty = CareDifficulty::tryFrom((string) $request->query('kinh-nghiem', ''));

        if ($careDifficulty) {
            $query->withCareDifficulty($careDifficulty);
        }

        $activeTaxon = null;

        if ($request->filled('loai')) {
            $activeTaxon = PlantTaxon::where('slug', $request->string('loai'))->first();

            if ($activeTaxon) {
                $query->inTaxon($activeTaxon);
            }
        }

        $activePromotion = null;

        if ($request->filled('promotion')) {
            $activePromotion = Promotion::query()
                ->where('slug', $request->string('promotion'))
                ->first();

            if ($activePromotion) {
                $query->whereHas(
                    'promotions',
                    fn ($q) => $q->where('promotions.id', $activePromotion->id)
                );
            }
        }

        $boSuuTap = FlowerCollection::tryFrom((string) $request->query('bo-suu-tap', ''));

        if ($boSuuTap) {
            $this->boSuuTap->apDung($query, $boSuuTap);
        }

        $search = $this->search->terms($request->query('q'));
        $sort = $request->string('sort')->toString();

        [$giaTu, $giaDen] = $this->khoangGiaDaChon($request);
        $khoangGia = $this->khoangGia($query, $search, $giaTu, $giaDen);

        if ($giaTu !== null || $giaDen !== null) {
            $query->effectivePriceBetween($giaTu, $giaDen);
        }

        $withoutKeywords = clone $query;

        $this->search->filter($query, $search);

        $this->applySort($query, $sort, $search);

        $products = $query->paginate(12)->withQueryString();

        $searchRelaxed = false;

        if ($products->isEmpty() && $search->hasMultipleTokens()) {
            $relaxed = $withoutKeywords;
            $this->search->filter($relaxed, $search, matchAll: false);
            $this->applySort($relaxed, $sort, $search);

            $products = $relaxed->paginate(12)->withQueryString();
            $searchRelaxed = $products->isNotEmpty();
        }

        if ($search->isNotEmpty()) {
            UserEvent::log(UserEventType::Search, $request, [
                'meta' => array_filter([
                    'q' => $search->original,
                    'used' => $search->wasCorrected() ? $search->suggestion() : null,
                    'relaxed' => $searchRelaxed ?: null,
                    'results' => $products->total(),
                ], fn ($v) => $v !== null),
            ]);
        }

        $categories = Category::query()
            ->plants()
            ->where('is_active', true)
            ->whereHas('products', fn ($q) => $q->whereIn('status', ['active', 'out_of_stock']))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $sellingForms = self::sellingForms();

        return view('shop.products.index', compact(
            'products',
            'categories',
            'sellingForms',
            'activePromotion',
            'search',
            'searchRelaxed',
            'traitFilters',
            'activeTaxon',
            'careDifficulty',
        ) + [
            'traitOptions' => $this->traitOptionsInUse(),

            'khoangGia' => $khoangGia,
            'giaTu' => $giaTu,
            'giaDen' => $giaDen,

            'boSuuTap' => $boSuuTap,
            'boSuuTapTieuDe' => $boSuuTap ? $this->boSuuTap->tieuDe($boSuuTap) : null,
            'boSuuTapMoTa' => $boSuuTap ? $this->boSuuTap->moTa($boSuuTap) : null,
            'soLuongBoSuuTap' => $this->boSuuTap->soLuong(),

            'careDifficulties' => app(\App\Services\Recommendation\PlantAdvisor::class)->availableDifficulties(),

            'moiThamSoLoc' => array_merge(
                ['q', 'category', 'selling_form', 'sort', 'kinh-nghiem', 'loai', 'promotion', 'bo-suu-tap', 'gia-tu', 'gia-den'],
                array_map(fn (TraitType $t) => $t->queryKey(), TraitType::filterable()),
            ),
        ]);
    }

    /** @return array{0: ?int, 1: ?int} */
    private function khoangGiaDaChon(Request $request): array
    {
        $doc = function (string $khoa) use ($request): ?int {
            $giaTri = $request->query($khoa);

            if (! is_string($giaTri)) {
                return null;
            }

            $so = preg_replace('/\D/', '', $giaTri);

            return $so === '' ? null : min((int) $so, 1_000_000_000);
        };

        $tu = $doc('gia-tu') ?: null;
        $den = $doc('gia-den');

        if ($tu !== null && $den !== null && $tu > $den) {
            [$tu, $den] = [$den, $tu];
        }

        return [$tu, $den];
    }

    /** Các khoảng giá gợi sẵn kèm số sản phẩm — đếm trên đúng bộ lọc khách đang dùng, bỏ khoảng trống. */
    private function khoangGia(Builder $query, SearchTerms $search, ?int $giaTu, ?int $giaDen): array
    {
        $out = [];

        foreach ((array) config('catalog.khoang_gia', []) as [$tu, $den]) {
            $dem = clone $query;
            $this->search->filter($dem, $search);
            $soLuong = $dem->effectivePriceBetween($tu, $den)->count();

            $dangChon = $giaTu === $tu && $giaDen === $den;

            if ($soLuong === 0 && ! $dangChon) {
                continue;
            }

            $out[] = [
                'tu' => $tu,
                'den' => $den,
                'nhan' => match (true) {
                    $tu === null => 'Dưới ' . Money::format($den),
                    $den === null => 'Từ ' . Money::format($tu),
                    default => Money::number($tu) . ' – ' . Money::format($den),
                },
                'so_luong' => $soLuong,
                'dang_chon' => $dangChon,
            ];
        }

        return $out;
    }

    private function traitOptionsInUse(): array
    {
        $rows = ProductTrait::query()
            ->select('trait_type', 'trait_value', DB::raw('count(*) as n'))
            ->whereIn('product_id', Product::query()
                ->mainCatalog()
                ->whereIn('status', ['active', 'out_of_stock'])
                ->select('id'))
            ->groupBy('trait_type', 'trait_value')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $out[$row->trait_type->value][$row->trait_value] = (int) $row->n;
        }

        return $out;
    }

    private function applySort(Builder $query, string $sort, SearchTerms $search): void
    {
        switch ($sort) {
            case 'price_asc':
                $query->orderByEffectivePrice('asc');

                return;

            case 'price_desc':
                $query->orderByEffectivePrice('desc');

                return;

            case 'popular':
                $query->orderByDesc('view_count');

                return;

            case 'noi_bat':
                $query->orderByDesc('is_featured')->latest();

                return;
        }

        if ($search->isNotEmpty()) {
            $this->search->orderByRelevance($query, $search);
        }

        $query->latest();
    }

    public function show(Request $request, Product $product): View
    {
        abort_unless(in_array($product->status, ['active', 'out_of_stock'], true), 404);

        $product->load([
            'category',
            'promotions',
            'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
            'traits',
            'blocks',
            'videos',
        ]);

        $product->loadAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating');
        $product->loadCount(['reviews as rating_count' => fn ($q) => $q->visible()]);

        $bangChung = app(\App\Services\Catalog\SocialProof::class);
        $daBan = $bangChung->banGanDay($product);
        $chiCon = $bangChung->chiCon($product, $product->variants->isNotEmpty());
        $quyCachPhoBien = $product->variants->isNotEmpty() ? $bangChung->quyCachBanChay($product) : null;

        $product->increment('view_count');

        $ref = (string) $request->query('ref', '');
        $ref = preg_match('/^[a-z0-9:_-]{1,40}$/', $ref) === 1 ? $ref : null;

        UserEvent::log(UserEventType::ProductView, $request, array_filter([
            'product_id' => $product->id,
            'category_id' => $product->category_id,
            'meta' => $ref ? ['ref' => $ref] : null,
        ], fn ($v) => $v !== null));

        $related = Product::query()
            ->with(['category', 'promotions',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->whereIn('status', ['active', 'out_of_stock'])
            ->take(4)
            ->get();

        $reviews = $product->reviews()
            ->visible()
            ->with('user')
            ->latest()
            ->paginate(5, ['*'], 'danh_gia');

        $reviewableOrder = Auth::check()
            ? Review::pendingOrderFor(Auth::id(), $product->id)
            : null;

        $recommendations = $this->recommendations->forViewer(
            Auth::id(),
            $request->session()->getId(),
            limit: 4,
            excludeIds: $related->pluck('id')->push($product->id)->all(),
        );

        $accessories = $this->advisor->accessoriesFor($product);

        $baiKhoe = \App\Models\CommunityPost::approved()
            ->where('product_id', $product->id)
            ->with(['user:id,name', 'media'])
            ->latest('approved_at')
            ->limit(4)
            ->get(['id', 'user_id', 'body', 'approved_at']);

        $qua = app(\App\Services\Gift\GiftResolver::class);
        $quaKem = $qua->choSanPham($product);
        $quaChuongTrinh = $qua->khuyenMaiQuaCho($product);

        return view('shop.products.show', compact(
            'quaKem',
            'quaChuongTrinh',
            'baiKhoe',
            'product',
            'related',
            'reviews',
            'reviewableOrder',
            'recommendations',
            'accessories',
            'daBan',
            'chiCon',
            'quyCachPhoBien',
        ));
    }
}
