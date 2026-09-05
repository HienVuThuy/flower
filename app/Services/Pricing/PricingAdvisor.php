<?php

namespace App\Services\Pricing;

use App\Enums\PriceSignal;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Đề xuất giá cho admin, dựa trên nhu cầu THẬT đo được.
 * ============================================================
 * ⚠️ KHÔNG ĐỌC BẢNG NHẬT KÝ CÁ NHÂN — xem QĐ-123. Nhật ký có thể chứa
 * đúng thứ hữu ích nhất cho việc định giá (khách ghi "đang chờ giảm còn
 * 300k"), và chính vì thế mà cám dỗ lớn. Khách được hứa là không ai đọc.
 *
 * ============================================================
 * NGUYÊN TẮC SỐ MỘT: KHÔNG ĐỦ DỮ LIỆU THÌ KHÔNG ĐỀ XUẤT.
 *
 * Cửa hàng này có vài chục đơn. Ở quy mô đó, phần lớn sản phẩm KHÔNG có
 * đủ số liệu để nói bất cứ điều gì về giá của nó — và câu trả lời đúng
 * là im lặng, không phải một đề xuất nghe cho có.
 *
 * Một công cụ luôn đưa ra được năm đề xuất cho mọi cửa hàng là một công
 * cụ đang bịa. Nguy hiểm hơn cả việc không có công cụ nào, vì admin sẽ
 * đổi giá thật theo nó.
 *
 * Ba tầng chặn:
 *
 *   1. `min_views` — sản phẩm chưa đủ lượt xem thì bỏ qua hoàn toàn.
 *   2. `confident_orders` — cả cửa hàng chưa đủ đơn thì vẫn chạy nhưng
 *      TỰ BÁO là mẫu mỏng, để admin đọc đề xuất với đúng mức tin cậy.
 *   3. Mỗi đề xuất phải mang theo con số sinh ra nó (`evidence`).
 *
 * ============================================================
 * KHÔNG ĐỀ XUẤT MỘT CON SỐ GIÁ CỤ THỂ.
 *
 * Muốn nói "nên bán 420.000đ" thì phải biết độ co giãn của cầu theo giá
 * — thứ chỉ đo được bằng cách thử nhiều mức giá trên nhiều nghìn lượt
 * mua. Ở đây không có dữ liệu đó, và sẽ không có.
 *
 * Thứ tính được THẬT là MỐC THAM CHIẾU: trung vị giá của những sản phẩm
 * CÙNG DANH MỤC ĐÃ BÁN ĐƯỢC. Đó là một con số có thật, admin đối chiếu
 * được, và nó không giả vờ là một lời tiên tri.
 */
class PricingAdvisor
{
    public function __construct(
        private readonly DemandSignals $signals,
    ) {
    }

    /**
     * Danh sách đề xuất, xếp theo mức đáng chú ý.
     *
     * @return array{
     *   suggestions: Collection<int, PriceSuggestion>,
     *   window_days: int,
     *   total_orders: int,
     *   thin_data: bool,
     *   examined: int,
     *   skipped_too_few_views: int,
     * }
     */
    public function suggest(int $limit = 12): array
    {
        $nhuCau = $this->signals->all();

        $moc = $this->mocGiaTheoDanhMuc();

        $minViews = (int) config('pricing-advisor.min_views');

        $boQua = 0;
        $deXuat = collect();

        foreach ($nhuCau as $d) {
            /*
             * CHẶN TẦNG MỘT.
             *
             * Sản phẩm chưa đủ lượt xem thì "0 đơn" không nói gì về giá —
             * nó chỉ nói rằng gần như chưa ai nhìn thấy món này. Vấn đề
             * khi đó là hiển thị, không phải giá, và đề xuất giảm giá sẽ
             * làm admin giảm giá một món đang lành lặn.
             *
             * Ngoại lệ: tồn kho nằm lâu KHÔNG cần lượt xem. "Còn 20 cái
             * trong kho, 60 ngày không bán được" là một sự thật đầy đủ,
             * không phụ thuộc vào việc có ai xem hay không.
             */
            $duLuotXem = $d->views >= $minViews;

            $tinHieu = $this->tinHieuCho($d, $duLuotXem, $moc);

            if ($tinHieu === null) {
                if (! $duLuotXem) {
                    $boQua++;
                }

                continue;
            }

            $deXuat->push($tinHieu);
        }

        return [
            'suggestions' => $deXuat
                ->sortBy(fn (PriceSuggestion $s) => [$s->signal->rank(), -$s->demand->views])
                ->take($limit)
                ->values(),
            'window_days' => $this->signals->days(),
            'total_orders' => $tongDon = $this->signals->totalOrders(),
            'thin_data' => $tongDon < (int) config('pricing-advisor.confident_orders'),
            'examined' => $nhuCau->count(),
            'skipped_too_few_views' => $boQua,
        ];
    }

    /* ================= LUẬT ================= */

    /**
     * Tín hiệu của một sản phẩm, hoặc null nếu số liệu chưa nói gì.
     *
     * MỖI NHÁNH DỰNG BẰNG CHỨNG NGAY TẠI CHỖ, không gom lại ở cuối. Gom
     * lại thì bằng chứng và điều kiện nằm cách nhau, và lần sửa sau đổi
     * điều kiện mà quên đổi bằng chứng — lúc đó công cụ nói dối mà vẫn
     * chạy đúng.
     *
     * @param  array<int, float>  $moc  trung vị giá theo category_id
     */
    private function tinHieuCho(ProductDemand $d, bool $duLuotXem, array $moc): ?PriceSuggestion
    {
        $cfg = config('pricing-advisor');
        $ton = $d->stock();

        /* ---- Tồn kho nằm lâu: không cần lượt xem ---- */
        $ngayKhongBan = $d->neverSold() ? $d->ageInDays() : $d->daysSinceLastSale();

        if (
            $ton !== null
            && $ton >= (int) $cfg['stale_min_stock']
            && $ngayKhongBan !== null
            && $ngayKhongBan >= (int) $cfg['stale_days']
        ) {
            $bangChung = [
                $d->neverSold()
                    ? sprintf('Chưa bán được lần nào kể từ khi thêm vào cửa hàng (%d ngày)', $ngayKhongBan)
                    : sprintf('Lần bán gần nhất cách đây %d ngày', $ngayKhongBan),
                sprintf('Còn %d trong kho', $ton),
                sprintf('%d lượt xem trong %d ngày qua', $d->views, $d->windowDays),
            ];

            // Đang giảm giá rồi mà vẫn nằm im: giảm sâu thêm khó mà giải
            // quyết được. Đây là một kết luận KHÁC, không phải mức độ
            // nặng hơn của cùng một kết luận.
            $tinHieu = $d->isDiscounted()
                ? PriceSignal::DiscountNotWorking
                : PriceSignal::StaleStock;

            if ($d->isDiscounted()) {
                $bangChung[] = 'Đang có chương trình khuyến mại chạy';
            }

            return new PriceSuggestion($d, $tinHieu, $bangChung, $this->mocCho($d, $moc));
        }

        if (! $duLuotXem) {
            return null;
        }

        $tiLeDon = $d->conversionRate();
        $tiLeGio = $d->cartRate();

        /* ---- Thêm giỏ nhiều nhưng ít đơn: nghẽn ở thanh toán ---- */
        if (
            $tiLeGio !== null
            && $tiLeGio >= (float) $cfg['high_cart_percent']
            && $tiLeDon !== null
            && $tiLeDon < (float) $cfg['low_conversion_percent']
        ) {
            return new PriceSuggestion($d, PriceSignal::CartNotCheckout, [
                sprintf('%d lượt xem, %d lần thêm giỏ (%.1f%%)', $d->views, $d->addToCarts, $tiLeGio),
                sprintf('Chỉ %d đơn (%.1f%%)', $d->ordersWith, $tiLeDon),
                'Khách đã bỏ vào giỏ — tức là đã chấp nhận giá',
            ], $this->mocCho($d, $moc));
        }

        /* ---- Quan tâm nhiều, không ai đặt ---- */
        if ($tiLeDon !== null && $tiLeDon < (float) $cfg['low_conversion_percent']) {
            $bangChung = [
                sprintf('%d lượt xem trong %d ngày qua', $d->views, $d->windowDays),
                $d->ordersWith === 0
                    ? 'Chưa có đơn nào trong khoảng này'
                    : sprintf('%d đơn (%.1f%%)', $d->ordersWith, $tiLeDon),
            ];

            if ($d->isDiscounted()) {
                // Đã giảm giá mà vẫn không chuyển đổi — lại là một kết
                // luận khác hẳn.
                $bangChung[] = 'Đang có chương trình khuyến mại chạy';

                return new PriceSuggestion($d, PriceSignal::DiscountNotWorking, $bangChung, $this->mocCho($d, $moc));
            }

            return new PriceSuggestion($d, PriceSignal::InterestNoSale, $bangChung, $this->mocCho($d, $moc));
        }

        /* ---- Bán tốt mà giá dưới mặt bằng danh mục ---- */
        $trungVi = $moc[$d->product->category_id] ?? null;
        $gia = $d->product->base_price === null ? null : (float) $d->product->base_price;

        if (
            $trungVi !== null
            && $gia !== null
            && $gia > 0
            && $d->ordersWith >= (int) $cfg['best_seller_orders']
            && ($trungVi - $gia) / $trungVi * 100 >= (float) $cfg['underpriced_percent']
        ) {
            return new PriceSuggestion($d, PriceSignal::UnderpricedBestSeller, [
                sprintf('%d đơn trong %d ngày qua', $d->ordersWith, $d->windowDays),
                sprintf('Đang bán %s', $this->tien($gia)),
                sprintf('Thấp hơn %.0f%% so với mặt bằng danh mục', ($trungVi - $gia) / $trungVi * 100),
            ], $this->mocCho($d, $moc));
        }

        return null;
    }

    /* ================= MỐC THAM CHIẾU ================= */

    /**
     * Trung vị giá của những sản phẩm ĐÃ BÁN ĐƯỢC, theo từng danh mục.
     *
     * DÙNG TRUNG VỊ, KHÔNG DÙNG TRUNG BÌNH: một lẵng hoa khai trương
     * 3.000.000đ nằm chung danh mục với chục bó hoa vài trăm nghìn sẽ kéo
     * trung bình lên tới mức không sản phẩm nào ở gần — và mọi món còn
     * lại đều bị gắn nhãn "dưới mặt bằng".
     *
     * CHỈ TÍNH SẢN PHẨM ĐÃ BÁN ĐƯỢC: mặt bằng giá phải là giá mà khách
     * THẬT SỰ đã trả tiền, không phải giá niêm yết của những món chưa ai
     * mua. Gộp cả hàng ế vào thì mốc tham chiếu bị kéo về phía đúng những
     * mức giá đang không hiệu quả.
     *
     * @return array<int, float> category_id => trung vị
     */
    private function mocGiaTheoDanhMuc(): array
    {
        $rows = Product::query()
            ->whereNotNull('base_price')
            ->where('status', 'active')
            ->whereHas('orderItems.order', fn ($q) => $q
                ->whereNull('orders.deleted_at')
                ->where('orders.status', '!=', \App\Enums\OrderStatus::Cancelled->value))
            ->get(['id', 'category_id', 'base_price']);

        $ket = [];

        foreach ($rows->groupBy('category_id') as $categoryId => $nhom) {
            /*
             * CẦN ÍT NHẤT BA SẢN PHẨM mới gọi là "mặt bằng".
             *
             * Trung vị của hai món là điểm giữa của đúng hai con số —
             * không phải một mặt bằng, chỉ là một phép chia đôi. Đưa ra
             * làm mốc tham chiếu là bịa một chuẩn từ chỗ chưa có chuẩn.
             */
            if ($nhom->count() < 3) {
                continue;
            }

            $gia = $nhom->pluck('base_price')->map(fn ($g) => (float) $g)->sort()->values();
            $n = $gia->count();

            $ket[(int) $categoryId] = $n % 2 === 1
                ? $gia[intdiv($n, 2)]
                : ($gia[$n / 2 - 1] + $gia[$n / 2]) / 2;
        }

        return $ket;
    }

    /** @param  array<int, float>  $moc */
    private function mocCho(ProductDemand $d, array $moc): ?string
    {
        $trungVi = $moc[$d->product->category_id] ?? null;

        if ($trungVi === null) {
            return null;
        }

        return sprintf(
            'Mặt bằng %s (trung vị hàng đã bán được): %s',
            $d->product->category?->name ?? 'danh mục',
            $this->tien($trungVi),
        );
    }

    private function tien(float $so): string
    {
        return \App\Services\Shop\Money::format($so);
    }
}
