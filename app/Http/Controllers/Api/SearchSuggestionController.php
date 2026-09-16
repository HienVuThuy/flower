<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Search\ProductSearch;
use App\Services\Search\SearchTerms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Gợi ý sản phẩm cho ô tìm kiếm trên thanh đầu trang. */
class SearchSuggestionController extends Controller
{
    private const LIMIT = 6;

    private const MIN_LENGTH = 2;

    public function __construct(
        private readonly ProductSearch $search,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $terms = $this->search->terms($request->query('q'));

        $raw = trim((string) $request->query('q'));

        if ($terms->isEmpty() || mb_strlen($raw) < self::MIN_LENGTH) {
            return $this->reply($terms, []);
        }

        $query = Product::query()
            ->with(['category', 'promotions'])
            ->whereIn('status', ['active', 'out_of_stock']);

        $this->search->filter($query, $terms);
        $this->search->orderByRelevance($query, $terms);

        $products = $query->limit(self::LIMIT)->get();

        if ($products->isEmpty() && $terms->hasMultipleTokens()) {
            $relaxed = Product::query()
                ->with(['category', 'promotions'])
                ->whereIn('status', ['active', 'out_of_stock']);

            $this->search->filter($relaxed, $terms, matchAll: false);
            $this->search->orderByRelevance($relaxed, $terms);

            $products = $relaxed->limit(self::LIMIT)->get();
        }

        return $this->reply($terms, $products->all());
    }

    private function reply(SearchTerms $terms, array $products): JsonResponse
    {
        return response()->json([
            'query' => $terms->original,

            'corrected' => $terms->wasCorrected() ? $terms->suggestion() : null,
            'alternative' => $terms->alternative,

            'items' => array_map(fn (Product $p) => [
                'name' => $p->name,
                'category' => $p->category?->name,
                'url' => route('shop.products.show', $p),
                'image' => $p->main_image ? asset('storage/'.$p->main_image) : null,
                'price' => $this->money($p->currentPrice()),
                'inStock' => $p->inStock(),
            ], $products),
        ]);
    }

    private function money(?string $amount): ?string
    {
        return $amount === null
            ? null
            : number_format((float) $amount, 0, ',', '.').'đ';
    }
}
