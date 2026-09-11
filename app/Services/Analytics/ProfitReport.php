<?php

namespace App\Services\Analytics;

use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Enums\StockReceiptStatus;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\StockReceiptItem;
use Illuminate\Support\Collection;

/**
 * Lãi gộp — CHỈ trên phần doanh thu có giá vốn thật.
 * ============================================================
 * GIÁ VỐN ĐẾN TỪ ĐÂU: phiếu nhập kho đã ghi sổ có điền giá
 * (`stock_receipt_items.unit_cost`). Không ước lượng bằng một tỉ lệ phần
 * trăm nghĩ ra, không lấy giá bán trừ đi một con số cho đẹp.
 *
 * ============================================================
 * BA QUY TẮC, và vì sao:
 *
 *   1. GIÁ VỐN BÌNH QUÂN GIA QUYỀN CỦA CÁC LẦN NHẬP TỚI NGÀY BÁN. Một lô
 *      nhập tuần sau không được quyết định giá vốn của hàng bán tuần này.
 *      Dòng bán TRƯỚC lần nhập có giá đầu tiên thì KHÔNG có giá vốn — hàng
 *      đó đến từ tồn kho cũ mà không ai biết đã mua bao nhiêu.
 *
 *   2. DÒNG KHÔNG CÓ GIÁ VỐN BỊ LOẠI khỏi lãi, và được ĐẾM, ĐỊNH GIÁ, NÓI
 *      RA. Tính lãi trên 20% doanh thu rồi gọi đó là "lãi của cửa hàng" là
 *      bịa. `ti_le_phu` nói con số lãi đang đứng trên bao nhiêu phần doanh
 *      thu.
 *
 *   3. DOANH THU CHƯA GỒM VAT. Giá bán đã gồm VAT (QĐ về thuế); VAT là tiền
 *      nộp nhà nước, không phải tiền của cửa hàng. Doanh thu một dòng =
 *      `line_total − discount_amount − tax_amount`. Giá vốn trên phiếu nhập
 *      được hiểu là CHƯA GỒM VAT ĐẦU VÀO — ô nhập liệu nói rõ điều đó.
 *
 * KHÔNG TÍNH "LÃI RÒNG". Hoàn tiền, bù phí ship, phí cổng thanh toán, mặt
 * bằng, nhân công đều là chi phí thật nhưng hệ thống không có đủ số liệu
 * cho hầu hết chúng. Hai khoản có số liệu (hoàn tiền, bù ship) được hiện
 * CẠNH lãi gộp để người đọc tự trừ, không gộp thành một con số trông như
 * lãi ròng.
 */
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

    /**
     * @return array{
     *     doanh_thu: string, doanh_thu_co_gia_von: string, gia_von: string, lai_gop: string,
     *     bien: float|null, ti_le_phu: float|null, dong_khong_gia_von: int, doanh_thu_khong_gia_von: string,
     *     theo_san_pham: Collection, can_nhap_gia_von: Collection, hoan_tien: string,
     *     co_phieu_nhap_co_gia: bool
     * }
     */
    public function baoCao(): array
    {
        $bangGia = $this->bangGiaVon();

        $dong = $this->khoang->apDung(
            OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.status', OrderStatus::Completed->value)
                ->whereNull('orders.deleted_at'),
            'orders.created_at',
        )->get([
            'order_items.order_id',
            'order_items.product_id',
            'order_items.product_variant_id',
            'order_items.product_name',
            'order_items.variant_name',
            'order_items.quantity',
            'order_items.line_total',
            'order_items.discount_amount',
            'order_items.tax_amount',
            'orders.created_at as ngay_dat',
            'orders.tax_amount as thue_cua_don',
        ]);

        /*
         * ĐƠN CHƯA CÓ SỐ LIỆU THUẾ (`orders.tax_amount` NULL): đặt trước khi
         * có tính thuế GTGT, hoặc lúc tính thuế đang tắt. Với chúng không có
         * gì để trừ, nên "doanh thu chưa VAT" thực chất vẫn GỒM VAT. Đếm và
         * nói ra — không để nhãn "chưa VAT" khẳng định điều không đúng.
         */
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

            $donGia = $this->giaVonTaiNgay($bangGia[$khoa] ?? [], $ngay);

            if ($donGia === null) {
                $soDongKhongGia++;
                $khongGia = bcadd($khongGia, $tien, 2);

                $canNhap[$khoa] ??= ['ten' => $ten, 'doanh_thu' => '0.00', 'so_luong' => 0];
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
        ];
    }

    /**
     * Mọi lần nhập có giá, theo đơn vị kho, xếp theo ngày nhập, kèm lũy kế.
     *
     * @return array<string, list<array{ngay: string, sl: int, tien: string}>>
     */
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
                // Lần nhập đầu tiên của mặt hàng: chưa có lũy kế nào. `end()` trên
                // một khoá chưa tồn tại là TypeError — trang Lợi nhuận sập đúng lúc
                // cửa hàng lập phiếu nhập có giá đầu tiên.
                $truoc = isset($bang[$khoa]) ? end($bang[$khoa]) : ['sl' => 0, 'tien' => '0.00'];

                /*
                 * Lũy kế CẢ DÒNG ÂM có giá: phiếu điều chỉnh "nhập nhầm 5 cái
                 * @48.000" phải kéo giá vốn về đúng như chưa từng nhập nhầm.
                 */
                $bang[$khoa][] = [
                    'ngay' => substr((string) $d->received_at, 0, 10),
                    'sl' => $truoc['sl'] + (int) $d->quantity,
                    'tien' => bcadd($truoc['tien'], bcmul((string) $d->unit_cost, (string) $d->quantity, 2), 2),
                ];
            });

        return $bang;
    }

    /**
     * Giá vốn bình quân tại một ngày bán, hoặc null nếu trước ngày đó chưa
     * có lần nhập nào có giá (hoặc lũy kế không dương).
     *
     * @param  list<array{ngay: string, sl: int, tien: string}>  $luyKe
     */
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

    /** Hoàn tiền đã xong cho đơn đã giao trong kỳ — hiện cạnh lãi, không trừ vào. */
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
