<?php

namespace App\Services\Analytics;

use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Models\FlowerLot;
use App\Models\OrderItem;

/**
 * Lãi gộp của hoa tươi — tính theo LÔ, ở mức KỲ.
 * ============================================================
 * VÌ SAO KHÔNG DÙNG CHUNG BÁO CÁO VỚI HÀNG THƯỜNG.
 *
 * ProfitReport ghép giá vốn vào TỪNG DÒNG ĐƠN: bán cái chậu này thì giá
 * vốn của chính cái chậu này là bao nhiêu. Hoa không làm được thế, và
 * không phải vì hệ thống thiếu sót:
 *
 *   **Không ai biết bó hoa bán hôm qua dùng cành của lô nào.**
 *
 * Ép một con số vào đó là bịa. Nên hoa có báo cáo riêng, ở mức kỳ:
 *
 *   Doanh thu hoa trong kỳ − tiền các lô đã đóng trong kỳ = lãi gộp hoa
 *
 * ============================================================
 * "ĐÃ ĐÓNG TRONG KỲ", KHÔNG PHẢI "ĐÃ MUA TRONG KỲ".
 *
 * Lô mua ngày 28 mà dùng sang đầu tháng sau thì tiền của nó thuộc tháng
 * sau — vì đó là lúc hoa thật sự được bán. Lấy theo ngày mua thì cuối
 * mỗi tháng lãi bị kéo xuống bởi lô vừa lấy về còn nguyên trong xô.
 *
 * HỆ QUẢ PHẢI NÓI RA: **quên đóng lô là giá vốn thấp hơn sự thật**, và
 * lãi gộp cao hơn sự thật. Báo cáo này đếm luôn số lô còn mở quá lâu và
 * hiện ra, thay vì để con số đẹp đẽ đứng một mình.
 */
class FlowerCostReport
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
     *     doanh_thu: string, gia_von: string, lai_gop: ?string,
     *     so_lo_dong: int, so_lo_con_mo: int, tien_lo_con_mo: string,
     *     lo_qua_han: int, hao_hut_trung_binh: ?float,
     * }
     */
    public function baoCao(): array
    {
        $doanhThu = $this->doanhThuHoa();
        $giaVon = $this->giaVonLoDaDong();

        $daDong = (clone $this->truyVanLoDaDong())->count();

        $conMo = FlowerLot::query()->dangDung();

        return [
            'doanh_thu' => $doanhThu,
            'gia_von' => $giaVon,

            /*
             * CHƯA ĐÓNG LÔ NÀO THÌ KHÔNG CÓ LÃI ĐỂ NÓI.
             *
             * null chứ không phải bằng doanh thu: "lãi gộp bằng đúng
             * doanh thu" là câu sai hoàn toàn, và nó là câu dễ tin nhất
             * vì trông như một cửa hàng lãi 100%.
             */
            'lai_gop' => $daDong > 0 ? bcsub($doanhThu, $giaVon, 2) : null,

            'so_lo_dong' => $daDong,
            'so_lo_con_mo' => (clone $conMo)->count(),
            'tien_lo_con_mo' => bcadd((string) (clone $conMo)->sum('total_cost'), '0', 2),

            'lo_qua_han' => (clone $conMo)
                ->where('purchased_at', '<', now()->subDays(\App\Services\Inventory\FlowerLotService::NGAY_NHAC_DONG)->toDateString())
                ->count(),

            'hao_hut_trung_binh' => $this->haoHutTrungBinh(),
        ];
    }

    /**
     * Doanh thu từ hoa tươi trong kỳ — chưa gồm VAT.
     *
     * Cùng định nghĩa với ProfitReport: `line_total − discount_amount −
     * tax_amount`, chỉ đơn đã giao. Hai báo cáo mà hai định nghĩa doanh
     * thu thì cộng lại không ra tổng của cửa hàng.
     */
    private function doanhThuHoa(): string
    {
        $q = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('products.product_type', ProductType::Flower->value)
            ->where('orders.status', OrderStatus::Completed->value)
            ->whereNull('orders.deleted_at');

        $this->khoang->apDung($q, 'orders.created_at');

        /*
         * CỘNG TỪNG DÒNG BẰNG bcmath, không cộng bằng SQL.
         *
         * SQL cộng bằng số thực dấu phẩy động; với tiền thì mỗi phép
         * cộng là một lần làm tròn, và tổng lệch vài đồng so với cùng
         * con số tính ở ProfitReport. Hai báo cáo lệch nhau vài đồng là
         * thứ không ai giải thích được và ai cũng nhớ.
         */
        $tong = '0.00';

        foreach ($q->get(['order_items.line_total', 'order_items.discount_amount', 'order_items.tax_amount']) as $d) {
            $tien = bcsub(
                bcsub((string) $d->line_total, (string) ($d->discount_amount ?? '0'), 2),
                (string) ($d->tax_amount ?? '0'),
                2,
            );

            $tong = bcadd($tong, $tien, 2);
        }

        return $tong;
    }

    private function truyVanLoDaDong(): \Illuminate\Database\Eloquent\Builder
    {
        $q = FlowerLot::query()->daDong();

        $this->khoang->apDung($q, 'closed_at');

        return $q;
    }

    private function giaVonLoDaDong(): string
    {
        return bcadd((string) (clone $this->truyVanLoDaDong())->sum('total_cost'), '0', 2);
    }

    /**
     * Hao hụt trung bình của các lô đóng trong kỳ, phần trăm.
     *
     * Tính trên TỔNG số lượng, không phải trung bình của các tỉ lệ: một
     * lô 2 bó hao sạch và một lô 200 bó hao 1 bó không phải "hao trung
     * bình 50%".
     */
    private function haoHutTrungBinh(): ?float
    {
        $lo = (clone $this->truyVanLoDaDong())->get(['quantity', 'hao_hut']);

        $tongSl = (float) $lo->sum(fn (FlowerLot $l) => (float) $l->quantity);

        if ($tongSl <= 0) {
            return null;
        }

        $tongHao = (float) $lo->sum(fn (FlowerLot $l) => (float) $l->hao_hut);

        return round($tongHao / $tongSl * 100, 1);
    }
}
