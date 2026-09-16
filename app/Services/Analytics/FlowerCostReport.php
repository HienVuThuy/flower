<?php

namespace App\Services\Analytics;

use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Models\FlowerLot;
use App\Models\OrderItem;

/** Lãi gộp của hoa tươi — tính theo LÔ, ở mức KỲ. */
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

    public function baoCao(): array
    {
        $doanhThu = $this->doanhThuHoa();
        $giaVon = $this->giaVonLoDaDong();

        $daDong = (clone $this->truyVanLoDaDong())->count();

        $conMo = FlowerLot::query()->dangDung();

        return [
            'doanh_thu' => $doanhThu,
            'gia_von' => $giaVon,

            'lai_gop' => $daDong > 0 ? bcsub($doanhThu, $giaVon, 2) : null,

            'so_lo_dong' => $daDong,
            'so_lo_con_mo' => (clone $conMo)->count(),
            'tien_lo_con_mo' => bcadd((string) (clone $conMo)->sum('total_cost'), '0', 2),

            'lo_qua_han' => (clone $conMo)
                ->where('purchased_at', '<', now()->subDays(\App\Services\Inventory\FlowerLotService::NGAY_NHAC_DONG)->toDateString())
                ->count(),

            'hao_hut_trung_binh' => $this->haoHutTrungBinh(),

            'tien_tra_lai' => $this->tienTraLai(),
            'so_lo_phai_tra' => (clone $this->truyVanLoDaDong())->whereNotNull('tra_lai_qty')->count(),
        ];
    }

    private function doanhThuHoa(): string
    {
        $q = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('products.product_type', ProductType::Flower->value)
            ->where('orders.status', OrderStatus::Completed->value)
            ->whereNull('orders.deleted_at');

        $this->khoang->apDung($q, 'orders.created_at');

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
        $tong = '0.00';

        foreach ((clone $this->truyVanLoDaDong())->get(['total_cost', 'tra_lai_tien']) as $l) {
            $tong = bcadd($tong, $l->tienThucTe(), 2);
        }

        return $tong;
    }

    private function tienTraLai(): string
    {
        $tong = '0.00';

        foreach ((clone $this->truyVanLoDaDong())->whereNotNull('tra_lai_tien')->get(['tra_lai_tien']) as $l) {
            $tong = bcadd($tong, (string) $l->tra_lai_tien, 2);
        }

        return $tong;
    }

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
