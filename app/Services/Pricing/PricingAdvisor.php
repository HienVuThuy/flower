<?php

namespace App\Services\Pricing;

use App\Enums\PriceSignal;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Đề xuất giá cho admin, dựa trên nhu cầu THẬT đo được.
 * ⚠️ KHÔNG ĐỌC BẢNG NHẬT KÝ CÁ NHÂN — xem QĐ-123. Nhật ký có thể chứa
 */
class PricingAdvisor
{
    public function __construct(
        private readonly DemandSignals $signals,
    ) {
    }

    public function suggest(int $limit = 12): array
    {
        $nhuCau = $this->signals->all();

        $moc = $this->mocGiaTheoDanhMuc();

        $minViews = (int) config('pricing-advisor.min_views');

        $boQua = 0;
        $deXuat = collect();

        foreach ($nhuCau as $d) {
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

    private function tinHieuCho(ProductDemand $d, bool $duLuotXem, array $moc): ?PriceSuggestion
    {
        $cfg = config('pricing-advisor');
        $ton = $d->stock();

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

        if ($tiLeDon !== null && $tiLeDon < (float) $cfg['low_conversion_percent']) {
            $bangChung = [
                sprintf('%d lượt xem trong %d ngày qua', $d->views, $d->windowDays),
                $d->ordersWith === 0
                    ? 'Chưa có đơn nào trong khoảng này'
                    : sprintf('%d đơn (%.1f%%)', $d->ordersWith, $tiLeDon),
            ];

            if ($d->isDiscounted()) {
                $bangChung[] = 'Đang có chương trình khuyến mại chạy';

                return new PriceSuggestion($d, PriceSignal::DiscountNotWorking, $bangChung, $this->mocCho($d, $moc));
            }

            return new PriceSuggestion($d, PriceSignal::InterestNoSale, $bangChung, $this->mocCho($d, $moc));
        }

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
