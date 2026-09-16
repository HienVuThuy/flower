<?php

namespace App\Services\Analytics;

use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Enums\StockReceiptStatus;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\StockReceiptItem;
use Illuminate\Support\Collection;

/** Lãi gộp — CHỈ trên phần doanh thu có giá vốn thật. */
class ProfitReport
{
    private KhoangThoiGian $khoang;

    public function __construct()
    {
        $this->khoang = new KhoangThoiGian();
    }

    public function trong(KhoangThoiGian $khoang): static
    {
        $this->khoang = $khoang;

        return $this;
    }

    public function baoCao(): array
    {
        $bangGia = $this->bangGiaVon();

        $dong = $this->khoang->apDung(
            OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.status', OrderStatus::Completed->value)
                ->whereNull('orders.deleted_at')

                ->where('order_items.is_gift', false)

                ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
                ->where(fn ($q) => $q
                    ->whereNull('products.product_type')
                    ->orWhere('products.product_type', '!=', \App\Enums\ProductType::Flower->value)),
            'orders.created_at',
        )->get([
            'order_items.order_id',
            'order_items.product_id',
            'order_items.product_variant_id',
            'order_items.product_name',
            'products.track_inventory as sp_theo_doi_ton',
            'order_items.variant_name',
            'order_items.quantity',
            'order_items.line_total',
            'order_items.discount_amount',
            'order_items.tax_amount',
            'orders.created_at as ngay_dat',
            'orders.tax_amount as thue_cua_don',
        ]);

        $chuaTachVat = '0.00';
        $soDongChuaTachVat = 0;

        $doanhThu = '0.00';
        $coGia = '0.00';
        $giaVon = '0.00';
        $khongGia = '0.00';
        $soDongKhongGia = 0;
        $theoSp = [];
        $canNhap = [];

        foreach ($dong as $d) {
            $tien = bcsub(
                bcsub((string) $d->line_total, (string) ($d->discount_amount ?? '0'), 2),
                (string) ($d->tax_amount ?? '0'),
                2,
            );

            $doanhThu = bcadd($doanhThu, $tien, 2);

            if ($d->thue_cua_don === null) {
                $soDongChuaTachVat++;
                $chuaTachVat = bcadd($chuaTachVat, $tien, 2);
            }

            $khoa = $d->product_id . ':' . ($d->product_variant_id ?? '');
            $ten = $d->product_name . ($d->variant_name ? ' — ' . $d->variant_name : '');
            $ngay = KhoangThoiGian::diaPhuong(\Illuminate\Support\Carbon::parse($d->ngay_dat, config('app.timezone')))->toDateString();

            $luyKe = $bangGia[$khoa]
                ?? ($d->product_variant_id === null ? ($bangGia[$d->product_id . ':*'] ?? []) : []);

            $donGia = $this->giaVonTaiNgay($luyKe, $ngay);

            if ($donGia === null) {
                $soDongKhongGia++;
                $khongGia = bcadd($khongGia, $tien, 2);

                $canNhap[$khoa] ??= [
                    'ten' => $ten,
                    'doanh_thu' => '0.00',
                    'so_luong' => 0,
                    'product_id' => $d->product_id,
                    'khong_theo_doi' => $d->product_id !== null
                        && $d->product_variant_id === null
                        && $d->sp_theo_doi_ton !== null
                        && ! (bool) $d->sp_theo_doi_ton,
                ];
                $canNhap[$khoa]['doanh_thu'] = bcadd($canNhap[$khoa]['doanh_thu'], $tien, 2);
                $canNhap[$khoa]['so_luong'] += (int) $d->quantity;

                continue;
            }

            $von = bcmul($donGia, (string) $d->quantity, 2);

            $coGia = bcadd($coGia, $tien, 2);
            $giaVon = bcadd($giaVon, $von, 2);

            $theoSp[$khoa] ??= ['ten' => $ten, 'so_luong' => 0, 'doanh_thu' => '0.00', 'gia_von' => '0.00'];
            $theoSp[$khoa]['so_luong'] += (int) $d->quantity;
            $theoSp[$khoa]['doanh_thu'] = bcadd($theoSp[$khoa]['doanh_thu'], $tien, 2);
            $theoSp[$khoa]['gia_von'] = bcadd($theoSp[$khoa]['gia_von'], $von, 2);
        }

        $laiGop = bcsub($coGia, $giaVon, 2);

        return [
            'doanh_thu' => $doanhThu,
            'doanh_thu_co_gia_von' => $coGia,
            'gia_von' => $giaVon,
            'lai_gop' => $laiGop,
            'bien' => bccomp($coGia, '0', 2) > 0 ? round((float) $laiGop / (float) $coGia * 100, 1) : null,
            'ti_le_phu' => bccomp($doanhThu, '0', 2) > 0 ? round((float) $coGia / (float) $doanhThu * 100, 1) : null,
            'dong_khong_gia_von' => $soDongKhongGia,
            'doanh_thu_khong_gia_von' => $khongGia,
            'theo_san_pham' => collect($theoSp)
                ->map(function ($r) {
                    $lai = bcsub($r['doanh_thu'], $r['gia_von'], 2);

                    return $r + [
                        'lai_gop' => $lai,
                        'bien' => bccomp($r['doanh_thu'], '0', 2) > 0 ? round((float) $lai / (float) $r['doanh_thu'] * 100, 1) : null,
                    ];
                })
                ->sortByDesc(fn ($r) => (float) $r['lai_gop'])
                ->values(),
            'can_nhap_gia_von' => collect($canNhap)->sortByDesc(fn ($r) => (float) $r['doanh_thu'])->take(15)->values(),
            'hoan_tien' => $this->hoanTienDonDaGiao(),
            'dong_chua_tach_vat' => $soDongChuaTachVat,
            'doanh_thu_chua_tach_vat' => $chuaTachVat,
            'co_phieu_nhap_co_gia' => $bangGia !== [],
            'chi_phi_qua' => $this->chiPhiQua($bangGia),
        ];
    }

    private function chiPhiQua(array $bangGia): array
    {
        $dong = $this->khoang->apDung(
            OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.status', OrderStatus::Completed->value)
                ->whereNull('orders.deleted_at')
                ->where('order_items.is_gift', true),
            'orders.created_at',
        )->get([
            'order_items.product_id',
            'order_items.product_variant_id',
            'order_items.quantity',
            'orders.created_at as ngay_dat',
        ]);

        $tien = '0.00';
        $chuaGia = 0;

        foreach ($dong as $d) {
            if ($d->product_id === null) {
                $chuaGia++;

                continue;
            }

            $luyKe = $bangGia[$d->product_id . ':' . ($d->product_variant_id ?? '')]
                ?? ($d->product_variant_id === null ? ($bangGia[$d->product_id . ':*'] ?? []) : []);

            $ngay = KhoangThoiGian::diaPhuong(\Illuminate\Support\Carbon::parse($d->ngay_dat, config('app.timezone')))->toDateString();
            $donGia = $this->giaVonTaiNgay($luyKe, $ngay);

            if ($donGia === null) {
                $chuaGia++;

                continue;
            }

            $tien = bcadd($tien, bcmul($donGia, (string) $d->quantity, 2), 2);
        }

        return ['tien' => $tien, 'so_dong_chua_gia' => $chuaGia];
    }

    private function bangGiaVon(): array
    {
        $bang = [];

        StockReceiptItem::query()
            ->join('stock_receipts', 'stock_receipts.id', '=', 'stock_receipt_items.stock_receipt_id')
            ->where('stock_receipts.status', StockReceiptStatus::Posted->value)
            ->whereNotNull('stock_receipt_items.unit_cost')
            ->orderBy('stock_receipts.received_at')
            ->orderBy('stock_receipt_items.id')
            ->get([
                'stock_receipt_items.product_id',
                'stock_receipt_items.product_variant_id',
                'stock_receipt_items.quantity',
                'stock_receipt_items.unit_cost',
                'stock_receipts.received_at',
            ])
            ->each(function ($d) use (&$bang) {
                $khoa = $d->product_id . ':' . ($d->product_variant_id ?? '');
                $truoc = isset($bang[$khoa]) ? end($bang[$khoa]) : ['sl' => 0, 'tien' => '0.00'];

                $bang[$khoa][] = [
                    'ngay' => substr((string) $d->received_at, 0, 10),
                    'sl' => $truoc['sl'] + (int) $d->quantity,
                    'tien' => bcadd($truoc['tien'], bcmul((string) $d->unit_cost, (string) $d->quantity, 2), 2),
                ];

                if ($d->product_variant_id !== null) {
                    $gop = $d->product_id . ':*';
                    $truocGop = isset($bang[$gop]) ? end($bang[$gop]) : ['sl' => 0, 'tien' => '0.00'];

                    $bang[$gop][] = [
                        'ngay' => substr((string) $d->received_at, 0, 10),
                        'sl' => $truocGop['sl'] + (int) $d->quantity,
                        'tien' => bcadd($truocGop['tien'], bcmul((string) $d->unit_cost, (string) $d->quantity, 2), 2),
                    ];
                }
            });

        return $bang;
    }

    private function giaVonTaiNgay(array $luyKe, string $ngay): ?string
    {
        $moc = null;

        foreach ($luyKe as $m) {
            if ($m['ngay'] > $ngay) {
                break;
            }

            $moc = $m;
        }

        if ($moc === null || $moc['sl'] <= 0) {
            return null;
        }

        return bcdiv($moc['tien'], (string) $moc['sl'], 2);
    }

    private function hoanTienDonDaGiao(): string
    {
        $donGiao = $this->khoang->apDung(
            \App\Models\Order::query()->where('status', OrderStatus::Completed),
            'created_at',
        )->select('id');

        return bcadd((string) Refund::query()
            ->where('status', RefundStatus::Completed->value)
            ->whereIn('order_id', $donGiao)
            ->sum('amount'), '0', 2);
    }
}
