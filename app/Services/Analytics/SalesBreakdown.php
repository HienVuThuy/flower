<?php

namespace App\Services\Analytics;

use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use Illuminate\Support\Collection;

/** Doanh thu cắt theo từng chiều: danh mục, tỉnh, khung giờ, khách mới/cũ. */
class SalesBreakdown
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

    public function theoDanhMuc(): array
    {
        $dong = $this->khoang->apDung(
            OrderItem::query()
                ->hangBan()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
                ->where('orders.status', OrderStatus::Completed->value)
                ->whereNull('orders.deleted_at'),
            'orders.created_at',
        )
            ->groupBy('products.category_id')
            ->selectRaw(
                'products.category_id as danh_muc,'
                .' SUM(order_items.line_total - order_items.discount_amount) as tien,'
                .' SUM(order_items.quantity) as sl,'
                .' COUNT(DISTINCT order_items.order_id) as don'
            )
            ->get();

        $ten = Category::whereIn('id', $dong->pluck('danh_muc')->filter())->pluck('name', 'id');

        $tong = '0.00';
        foreach ($dong as $d) {
            $tong = bcadd($tong, (string) $d->tien, 2);
        }

        $donGiao = $this->khoang->apDung(
            Order::query()->where('status', OrderStatus::Completed),
            'created_at',
        );

        $maTrenDon = (string) (clone $donGiao)->sum('coupon_discount');
        $maTrenDong = (string) $this->khoang->apDung(
            OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.status', OrderStatus::Completed->value)
                ->whereNull('orders.deleted_at'),
            'orders.created_at',
        )->sum('order_items.discount_amount');

        $chuaChia = bcsub(bcadd($maTrenDon, '0', 2), bcadd($maTrenDong, '0', 2), 2);

        return [
            'dong' => $dong
                ->map(fn ($d) => [
                    'ten' => $d->danh_muc === null
                        ? '(sản phẩm đã xoá)'
                        : (string) ($ten[$d->danh_muc] ?? '(danh mục đã xoá)'),
                    'doanh_thu' => bcadd((string) $d->tien, '0', 2),
                    'so_luong' => (int) $d->sl,
                    'so_don' => (int) $d->don,
                    'ti_le' => bccomp($tong, '0', 2) > 0
                        ? round((float) $d->tien / (float) $tong * 100, 1)
                        : null,
                ])
                ->sortByDesc(fn ($d) => (float) $d['doanh_thu'])
                ->values(),
            'tong' => $tong,
            'ma_giam_chua_chia' => bccomp($chuaChia, '0', 2) > 0 ? $chuaChia : '0.00',
        ];
    }

    public function theoTinh(): Collection
    {
        $don = $this->khoang->apDung(
            Order::query()->where('status', OrderStatus::Completed),
            'created_at',
        )->get(['id', 'shipping_province', 'grand_total']);

        $hoan = Refund::query()
            ->where('status', RefundStatus::Completed->value)
            ->whereIn('order_id', $don->pluck('id'))
            ->groupBy('order_id')
            ->selectRaw('order_id, SUM(amount) as tien')
            ->pluck('tien', 'order_id');

        return $don
            ->groupBy(fn ($o) => TenTinh::khoa($o->shipping_province))
            ->map(function (Collection $nhom) use ($hoan) {
                $doanhThu = '0.00';
                $daHoan = '0.00';

                foreach ($nhom as $o) {
                    $doanhThu = bcadd($doanhThu, (string) $o->grand_total, 2);
                    $daHoan = bcadd($daHoan, (string) ($hoan[$o->id] ?? '0'), 2);
                }

                $thuan = bcsub($doanhThu, $daHoan, 2);

                return [
                    'ten' => TenTinh::nhan($nhom->pluck('shipping_province')),
                    'so_don' => $nhom->count(),
                    'doanh_thu' => $doanhThu,
                    'hoan_tien' => $daHoan,
                    'thuan' => $thuan,
                    'trung_binh' => bcdiv($thuan, (string) $nhom->count(), 2),
                ];
            })
            ->sortByDesc(fn ($d) => (float) $d['thuan'])
            ->values();
    }

    public function theoKhungGio(): array
    {
        $o = array_fill(0, 7, array_fill(0, 24, 0));

        $this->khoang->apDung(Order::query(), 'created_at')
            ->pluck('created_at')
            ->each(function ($t) use (&$o) {
                $dp = KhoangThoiGian::diaPhuong($t);
                $o[$dp->isoWeekday() - 1][(int) $dp->format('G')]++;
            });

        $theoThu = array_map('array_sum', $o);
        $theoGio = array_map(fn (int $h) => array_sum(array_column($o, $h)), range(0, 23));

        return [
            'o' => $o,
            'theo_thu' => $theoThu,
            'theo_gio' => $theoGio,
            'tong' => array_sum($theoThu),
            'cao_nhat' => max(array_map('max', $o)),
        ];
    }

    public function khachMoiVaQuayLai(): array
    {
        $donDau = Order::query()
            ->where('status', OrderStatus::Completed)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->selectRaw('user_id, MIN(id) as dau, COUNT(*) as so_don')
            ->get()
            ->keyBy('user_id');

        $trongKy = $this->khoang->apDung(
            Order::query()->where('status', OrderStatus::Completed),
            'created_at',
        )->get(['id', 'user_id', 'grand_total']);

        $ket = [
            'moi' => ['khach' => [], 'don' => 0, 'doanh_thu' => '0.00'],
            'quay_lai' => ['khach' => [], 'don' => 0, 'doanh_thu' => '0.00'],
            'vang_lai' => ['don' => 0, 'doanh_thu' => '0.00'],
        ];

        foreach ($trongKy as $o) {
            if ($o->user_id === null) {
                $ket['vang_lai']['don']++;
                $ket['vang_lai']['doanh_thu'] = bcadd($ket['vang_lai']['doanh_thu'], (string) $o->grand_total, 2);

                continue;
            }

            $nhom = (int) $donDau[$o->user_id]->dau === (int) $o->id ? 'moi' : 'quay_lai';

            $ket[$nhom]['khach'][$o->user_id] = true;
            $ket[$nhom]['don']++;
            $ket[$nhom]['doanh_thu'] = bcadd($ket[$nhom]['doanh_thu'], (string) $o->grand_total, 2);
        }

        foreach (['moi', 'quay_lai'] as $nhom) {
            $ket[$nhom]['khach'] = count($ket[$nhom]['khach']);
        }

        $coDon = $donDau->count();
        $muaLai = $donDau->filter(fn ($r) => (int) $r->so_don >= 2)->count();

        return $ket + [
            'khach_co_don' => $coDon,
            'khach_mua_lai' => $muaLai,
            'ti_le_mua_lai' => $coDon > 0 ? round($muaLai / $coDon * 100, 1) : null,
        ];
    }
}
