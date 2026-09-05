<?php

namespace App\Services\Recommendation;

use App\Models\Product;
use App\Models\UserEvent;
use Illuminate\Support\Collection;

/**
 * Gợi ý sản phẩm cá nhân hoá (Guide §11 "User → Gợi ý cá nhân hoá", §9, §25).
 * ============================================================
 * ⚠️ KHÔNG ĐƯỢC ĐỌC BẢNG NHẬT KÝ CÁ NHÂN. Xem chú thích đầu
 * `TasteProfile` và QĐ-123. `JournalPrivacyTest` canh điều này ở tầng
 * SQL và sẽ báo đỏ kèm đúng câu truy vấn vi phạm.
 *
 * ============================================================
 * VÌ SAO KHÔNG DÙNG LỌC CỘNG TÁC ("người mua X cũng mua Y"):
 * Đó là cách làm đúng khi có hàng chục nghìn phiên. Ở đây đo được nhiều
 * nhất vài chục sự kiện cho một tài khoản — với lượng đó, "người mua X
 * cũng mua Y" chỉ đang khuếch đại nhiễu, và tệ hơn là nó TRÔNG như một
 * hệ thống thông minh trong khi kết quả gần như ngẫu nhiên.
 *
 * CÁCH LÀM Ở ĐÂY: dựa trên hành vi của chính người đang xem.
 *
 *   1. Dựng CHÂN DUNG SỞ THÍCH từ những gì họ vừa xem, thêm giỏ, thích,
 *      mua — trên bốn trục: danh mục, hình thức bán, đặc điểm, phân loại
 *      thực vật. (`TasteProfile`)
 *   2. Lọc sơ bộ trong SQL để lấy những sản phẩm CÓ THỂ hợp, bỏ những
 *      thứ họ đã xem rồi.
 *   3. Chấm điểm từng ứng viên theo chân dung, lấy tốp đầu.
 *   4. Thiếu thì bù bằng sản phẩm phổ biến — và NÓI RÕ đó là phương án
 *      bù, không giả vờ là gợi ý riêng.
 *
 * ĐẶC ĐIỂM VÀ PHÂN LOẠI LÀ HAI TRỤC MỚI. Trước đây bộ máy chỉ biết danh
 * mục và hình thức bán, nên hai chậu sen đá — một xanh một tím — với nó
 * là giống hệt nhau, dù cửa hàng đã có sẵn dữ liệu màu, dáng, môi trường
 * sống và loài cho phần lớn sản phẩm.
 */
class RecommendationService
{
    /** Số sự kiện gần nhất được xét. Cũ hơn thì sở thích có thể đã đổi. */
    private const HISTORY_SIZE = 60;

    /** Số giá trị mỗi trục được đưa vào câu lọc sơ bộ. */
    private const TOP_MOI_TRUC = 3;

    private const POPULAR_REASON = 'Được nhiều người xem';

    /**
     * Gợi ý cho một người xem cụ thể.
     *
     * Nhận cả `$userId` lẫn `$sessionId` để chạy được cho KHÁCH VÃNG LAI.
     * Phần lớn người vào xem chưa đăng nhập; gợi ý chỉ cho người có tài
     * khoản thì gần như không bao giờ chạy.
     *
     * @param  array<int, int>  $excludeIds  sản phẩm ĐANG hiện ở nơi khác
     *                                       trên cùng trang, không gợi lại
     * @return array{items: Collection<int, array{product: Product, reason: string}>,
     *               personalized: bool}
     */
    public function forViewer(
        ?int $userId,
        ?string $sessionId,
        int $limit = 4,
        array $excludeIds = [],
    ): array {
        $history = $this->history($userId, $sessionId);

        /*
         * $excludeIds giải quyết một rủi ro đã ghi trong báo cáo trước:
         * catalog nhỏ, nên khối "Gợi ý cho bạn" rất dễ hiện lại đúng thứ
         * đang nằm ngay bên trên ở khối "Nổi bật" hoặc "Sản phẩm liên
         * quan". Lặp lại như vậy vừa phí chỗ vừa làm gợi ý trông như hỏng.
         *
         * Người gọi truyền vào vì chỉ họ biết trên trang của mình đang có
         * gì — lớp này không nhìn thấy trang.
         */
        $excluded = collect($excludeIds)->filter()->unique();

        if ($history->isEmpty()) {
            // Chưa biết gì về người này — không giả vờ là có.
            return [
                'items' => $this->popular($limit, $excluded),
                'personalized' => false,
            ];
        }

        $taste = new TasteProfile($history);

        if ($taste->isEmpty()) {
            // Có sự kiện nhưng toàn loại không mang tín hiệu (tìm kiếm
            // suông). Cũng là "chưa biết gì" — nói thật như vậy.
            return [
                'items' => $this->popular($limit, $excluded),
                'personalized' => false,
            ];
        }

        $seenProductIds = $history->pluck('product_id')->filter()->unique()->merge($excluded)->unique();

        $items = $this->matching($taste, $seenProductIds, $limit);

        /*
         * Không đủ sản phẩm khớp thì bù bằng phổ biến. Cửa hàng có vài
         * chục sản phẩm nên chuyện này xảy ra thường xuyên — thà bù còn
         * hơn hiện một khối trống lỗ chỗ.
         */
        if ($items->count() < $limit) {
            $exclude = $seenProductIds->merge($items->pluck('product.id'));

            $items = $items->concat(
                $this->popular($limit - $items->count(), $exclude)
            );
        }

        return [
            'items' => $items->values(),
            // Chỉ gọi là "cá nhân hoá" khi có ÍT NHẤT một mục thực sự
            // đến từ hành vi, không phải toàn hàng bù.
            'personalized' => $items->contains(fn ($i) => $i['reason'] !== self::POPULAR_REASON),
        ];
    }

    /* ================= NỘI BỘ ================= */

    /**
     * Lịch sử hành vi gần đây, kèm những cột của sản phẩm dùng để chấm
     * điểm.
     *
     * Join sang `products` ngay trong truy vấn thay vì nạp quan hệ sau:
     * chỉ cần vài cột, và làm vậy tránh một vòng truy vấn phụ.
     */
    private function history(?int $userId, ?string $sessionId): Collection
    {
        if (! $userId && ! $sessionId) {
            return collect();
        }

        return UserEvent::query()
            ->leftJoin('products', 'products.id', '=', 'user_events.product_id')
            ->where(function ($q) use ($userId, $sessionId) {
                /*
                 * Người đã đăng nhập được xét CẢ hành vi lúc chưa đăng
                 * nhập trong cùng phiên — họ vừa duyệt vừa đăng nhập là
                 * chuyện bình thường, cắt đôi lịch sử là mất tín hiệu.
                 */
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

    /**
     * Sản phẩm hợp với chân dung sở thích, xếp theo độ hợp.
     *
     * HAI BƯỚC — lọc sơ bộ trong SQL rồi chấm điểm trong PHP.
     *
     * Chấm điểm phải làm trong PHP: công thức bốn trục có chuẩn hoá không
     * viết thành `ORDER BY` được mà vẫn đọc hiểu nổi. Nhưng KHÔNG được vì
     * thế mà nạp cả bảng sản phẩm về — nên câu lọc sơ bộ thu hẹp trước
     * bằng đúng những giá trị người này quan tâm.
     */
    private function matching(TasteProfile $taste, Collection $excludeIds, int $limit): Collection
    {
        $categoryIds = $taste->categoryScores->keys()->take(self::TOP_MOI_TRUC);
        $forms = $taste->formScores->keys()->take(self::TOP_MOI_TRUC);
        $taxonIds = $taste->taxonScores->keys()->take(self::TOP_MOI_TRUC);

        // Nhãn được lấy rộng hơn: một sở thích màu sắc rõ rệt không nên
        // bị loại chỉ vì người này cũng đang xem nhiều danh mục.
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
            /*
             * Bỏ những sản phẩm lọt lưới sơ bộ nhưng chấm ra 0 điểm, và
             * những sản phẩm không nói được lý do.
             *
             * Một gợi ý không giải thích được vì sao nó ở đó thì với
             * khách nó chỉ là một sản phẩm ngẫu nhiên — đúng thứ khối
             * "Gợi ý cho bạn" hứa là không phải.
             */
            ->filter(fn (array $r) => $r['score'] > 0 && $r['reason'] !== null)
            ->sortByDesc('score')
            ->take($limit)
            ->map(fn (array $r) => ['product' => $r['product'], 'reason' => $r['reason']])
            ->values();
    }

    /** Sản phẩm được xem nhiều nhất — phương án bù khi chưa biết gì. */
    private function popular(int $limit, Collection $excludeIds): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        return $this->baseQuery()
            ->whereNotIn('products.id', $excludeIds->filter()->all() ?: [0])
            // view_count là cột đếm sẵn trên products, không phải đếm lại
            // từ user_events — nhanh hơn và đủ đúng cho việc bù.
            ->orderByDesc('view_count')
            ->limit($limit)
            ->get()
            ->map(fn (Product $p) => ['product' => $p, 'reason' => self::POPULAR_REASON]);
    }

    /**
     * Truy vấn nền: chỉ sản phẩm đang bán, nạp sẵn mọi thứ thẻ sản phẩm
     * và việc chấm điểm cần, để không sinh N+1 khi Blade lặp.
     *
     * `traits` nằm trong danh sách nạp sẵn vì `TasteProfile::match()`
     * đọc quan hệ đó cho từng ứng viên — thiếu nó thì mỗi sản phẩm là
     * một truy vấn.
     */
    private function baseQuery()
    {
        return Product::query()
            ->with(['category', 'promotions', 'traits'])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            /*
             * CHỈ GỢI Ý HÀNG CHÍNH — hoa và cây cảnh.
             *
             * Gợi ý là lời MỜI của cửa hàng. Mời khách xem một gói phân
             * bón khi họ chưa mua cây nào là mời sai thứ: phân bón chỉ có
             * nghĩa sau khi đã có cây. Vật tư được bán ở đúng chỗ của nó
             * — khối "mua kèm" trên trang sản phẩm và trong giỏ hàng, nơi
             * khách ĐÃ chọn cây.
             */
            ->mainCatalog()
            ->where('status', 'active')
            /*
             * KHÔNG gợi ý hàng đang hết.
             *
             * Trang danh sách vẫn hiện hàng hết vì khách chủ động vào tìm
             * — thấy "tạm hết hàng" còn hơn tưởng cửa hàng không bán. Còn
             * gợi ý là CỬA HÀNG chủ động mời: mời một thứ không mua được
             * là lãng phí chỗ và làm khách cụt hứng.
             *
             * Sản phẩm không quản lý tồn kho (hoa làm theo đơn) luôn được
             * coi là còn — chúng không có khái niệm hết hàng.
             */
            ->where(function ($q) {
                $q->where('track_inventory', false)
                    ->orWhere('stock_quantity', '>', 0);
            });
    }
}
