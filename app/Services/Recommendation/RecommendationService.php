<?php

namespace App\Services\Recommendation;

use App\Models\Product;
use App\Models\UserEvent;
use Illuminate\Support\Collection;

/**
 * Gợi ý sản phẩm cá nhân hoá (Guide §11 "User → Gợi ý cá nhân hoá", §9, §25).
 * ⚠️ KHÔNG ĐƯỢC ĐỌC BẢNG NHẬT KÝ CÁ NHÂN.
 */
class RecommendationService
{
    private const HISTORY_SIZE = 60;

    private const TOP_MOI_TRUC = 3;

    private const POPULAR_REASON = 'Được nhiều người xem';

    public function forViewer(
        ?int $userId,
        ?string $sessionId,
        int $limit = 4,
        array $excludeIds = [],
    ): array {
        $history = $this->history($userId, $sessionId);

        $excluded = collect($excludeIds)->filter()->unique();

        if ($history->isEmpty()) {
            return [
                'items' => $this->popular($limit, $excluded),
                'personalized' => false,
            ];
        }

        $taste = new TasteProfile($history);

        if ($taste->isEmpty()) {
            return [
                'items' => $this->popular($limit, $excluded),
                'personalized' => false,
            ];
        }

        $seenProductIds = $history->pluck('product_id')->filter()->unique()->merge($excluded)->unique();

        $items = $this->matching($taste, $seenProductIds, $limit);

        if ($items->count() < $limit) {
            $exclude = $seenProductIds->merge($items->pluck('product.id'));

            $items = $items->concat(
                $this->popular($limit - $items->count(), $exclude)
            );
        }

        return [
            'items' => $items->values(),
            'personalized' => $items->contains(fn ($i) => $i['reason'] !== self::POPULAR_REASON),
        ];
    }

    private function history(?int $userId, ?string $sessionId): Collection
    {
        if (! $userId && ! $sessionId) {
            return collect();
        }

        return UserEvent::query()
            ->leftJoin('products', 'products.id', '=', 'user_events.product_id')
            ->where(function ($q) use ($userId, $sessionId) {
                if ($userId) {
                    $q->orWhere('user_events.user_id', $userId);
                }

                if ($sessionId) {
                    $q->orWhere('user_events.session_id', $sessionId);
                }
            })
            ->orderByDesc('user_events.id')
            ->limit(self::HISTORY_SIZE)
            ->get([
                'user_events.event_type',
                'user_events.product_id',
                'user_events.category_id',
                'products.selling_form',
                'products.taxon_id',
            ]);
    }

    private function matching(TasteProfile $taste, Collection $excludeIds, int $limit): Collection
    {
        $categoryIds = $taste->categoryScores->keys()->take(self::TOP_MOI_TRUC);
        $forms = $taste->formScores->keys()->take(self::TOP_MOI_TRUC);
        $taxonIds = $taste->taxonScores->keys()->take(self::TOP_MOI_TRUC);

        $nhan = $taste->traitScores->keys()->take(self::TOP_MOI_TRUC * 2)
            ->map(fn (string $khoa) => explode(':', $khoa, 2));

        if ($categoryIds->isEmpty() && $forms->isEmpty() && $taxonIds->isEmpty() && $nhan->isEmpty()) {
            return collect();
        }

        $products = $this->baseQuery()
            ->whereNotIn('products.id', $excludeIds->all() ?: [0])
            ->where(function ($q) use ($categoryIds, $forms, $taxonIds, $nhan) {
                if ($categoryIds->isNotEmpty()) {
                    $q->orWhereIn('category_id', $categoryIds->all());
                }

                if ($forms->isNotEmpty()) {
                    $q->orWhereIn('selling_form', $forms->all());
                }

                if ($taxonIds->isNotEmpty()) {
                    $q->orWhereIn('taxon_id', $taxonIds->all());
                }

                foreach ($nhan as [$loai, $giaTri]) {
                    $q->orWhereHas(
                        'traits',
                        fn ($t) => $t->where('trait_type', $loai)->where('trait_value', $giaTri),
                    );
                }
            })
            ->get();

        return $products
            ->map(function (Product $p) use ($taste) {
                ['score' => $diem, 'reason' => $lyDo] = $taste->match($p);

                return ['product' => $p, 'score' => $diem, 'reason' => $lyDo];
            })
            ->filter(fn (array $r) => $r['score'] > 0 && $r['reason'] !== null)
            ->sortByDesc('score')
            ->take($limit)
            ->map(fn (array $r) => ['product' => $r['product'], 'reason' => $r['reason']])
            ->values();
    }

    private function popular(int $limit, Collection $excludeIds): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        return $this->baseQuery()
            ->whereNotIn('products.id', $excludeIds->filter()->all() ?: [0])
            ->orderByDesc('view_count')
            ->limit($limit)
            ->get()
            ->map(fn (Product $p) => ['product' => $p, 'reason' => self::POPULAR_REASON]);
    }

    private function baseQuery()
    {
        return Product::query()
            ->with(['category', 'promotions', 'traits', 'variants'])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->mainCatalog()
            ->where('status', 'active')
            ->where(function ($q) {
                $q->where('track_inventory', false)
                    ->orWhere('stock_quantity', '>', 0);
            });
    }
}
