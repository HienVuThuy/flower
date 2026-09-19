<?php

namespace App\Services\Analytics;

use App\Enums\ExchangeStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use App\Enums\UserRole;
use App\Models\Exchange;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Collection;

/** Báo cáo doanh thu toàn thời gian theo ngày / tháng / năm / danh mục / phương thức — cùng định nghĩa với trang Phân tích. */
class RevenueReport
{
    private ?Collection $ngay = null;

    public function tongQuan(): array
    {
        $ngay = $this->theoNgay();
        $doanhThu = $ngay->reduce(fn ($c, $d) => bcadd($c, $d['doanh_thu'], 2), '0.00');
        $hoan = $ngay->reduce(fn ($c, $d) => bcadd($c, $d['hoan'], 2), '0.00');
        $bu = $ngay->reduce(fn ($c, $d) => bcadd($c, $d['bu'], 2), '0.00');

        return [
            'tong_don' => Order::count(),
            'don_da_giao' => (int) $ngay->sum('so_don'),
            'tong_khach' => User::where('role', UserRole::Customer->value)->count(),
            'doanh_thu' => $doanhThu,
            'da_hoan' => $hoan,
            'bu_doi_hang' => $bu,
            'thuan' => bcadd(bcsub($doanhThu, $hoan, 2), $bu, 2),
        ];
    }

    public function theoDanhMuc(): array
    {
        return (new SalesBreakdown())->theoDanhMuc();
    }

    public function theoNgay(): Collection
    {
        if ($this->ngay !== null) {
            return $this->ngay;
        }

        $don = Order::query()
            ->where('status', OrderStatus::Completed->value)
            ->get(['id', 'created_at', 'grand_total']);

        $hoan = Refund::query()
            ->where('status', RefundStatus::Completed->value)
            ->whereIn('order_id', $don->pluck('id'))
            ->selectRaw('order_id, SUM(amount) as tien')
            ->groupBy('order_id')
            ->pluck('tien', 'order_id');

        $bu = Exchange::query()
            ->where('status', ExchangeStatus::HoanTat->value)
            ->whereIn('order_id', $don->pluck('id'))
            ->selectRaw('order_id, SUM(da_thu) as tien')
            ->groupBy('order_id')
            ->pluck('tien', 'order_id');

        return $this->ngay = $don
            ->groupBy(fn (Order $o) => KhoangThoiGian::diaPhuong($o->created_at)->toDateString())
            ->map(function (Collection $ds, string $ngay) use ($hoan, $bu) {
                $doanhThu = $ds->reduce(fn ($c, Order $o) => bcadd($c, (string) $o->grand_total, 2), '0.00');
                $daHoan = $ds->reduce(fn ($c, Order $o) => bcadd($c, (string) ($hoan[$o->id] ?? '0'), 2), '0.00');
                $buThem = $ds->reduce(fn ($c, Order $o) => bcadd($c, (string) ($bu[$o->id] ?? '0'), 2), '0.00');

                return [
                    'ky' => $ngay,
                    'so_don' => $ds->count(),
                    'doanh_thu' => $doanhThu,
                    'hoan' => $daHoan,
                    'bu' => $buThem,
                    'thuan' => bcadd(bcsub($doanhThu, $daHoan, 2), $buThem, 2),
                ];
            })
            ->sortKeys()
            ->values();
    }

    public function theoThang(): Collection
    {
        return $this->gop(fn (string $ngay) => substr($ngay, 0, 7));
    }

    public function theoNam(): Collection
    {
        return $this->gop(fn (string $ngay) => substr($ngay, 0, 4));
    }

    public function theoPhuongThuc(): Collection
    {
        $tien = Order::query()
            ->where('status', OrderStatus::Completed->value)
            ->selectRaw('payment_method, SUM(grand_total) as tien, COUNT(*) as so')
            ->groupBy('payment_method')
            ->get()
            ->keyBy(fn ($d) => $d->payment_method instanceof PaymentMethod ? $d->payment_method->value : (string) $d->payment_method);

        return collect(PaymentMethod::cases())->map(fn (PaymentMethod $pt) => [
            'phuong_thuc' => $pt,
            'so_don' => (int) ($tien[$pt->value]->so ?? 0),
            'doanh_thu' => bcadd((string) ($tien[$pt->value]->tien ?? '0'), '0', 2),
        ]);
    }

    public function bieuDoNgay(int $soNgay = 30): Collection
    {
        $theoNgay = $this->theoNgay()->keyBy('ky');
        $homNay = now(KhoangThoiGian::muiGio())->startOfDay();

        return collect(range($soNgay - 1, 0))->map(function (int $lui) use ($homNay, $theoNgay) {
            $ngay = $homNay->copy()->subDays($lui);

            return ['label' => $ngay->format('d/m'), 'value' => (float) ($theoNgay[$ngay->toDateString()]['thuan'] ?? 0)];
        });
    }

    public function bieuDoThang(int $soThang = 12): Collection
    {
        $theoThang = $this->theoThang()->keyBy('ky');
        $thangNay = now(KhoangThoiGian::muiGio())->startOfMonth();

        return collect(range($soThang - 1, 0))->map(function (int $lui) use ($thangNay, $theoThang) {
            $thang = $thangNay->copy()->subMonthsNoOverflow($lui);

            return ['label' => $thang->format('m/Y'), 'value' => (float) ($theoThang[$thang->format('Y-m')]['thuan'] ?? 0)];
        });
    }

    private function gop(callable $khoa): Collection
    {
        return $this->theoNgay()
            ->groupBy(fn (array $d) => $khoa($d['ky']))
            ->map(fn (Collection $ds, string $ky) => [
                'ky' => $ky,
                'so_don' => (int) $ds->sum('so_don'),
                'doanh_thu' => $ds->reduce(fn ($c, $d) => bcadd($c, $d['doanh_thu'], 2), '0.00'),
                'hoan' => $ds->reduce(fn ($c, $d) => bcadd($c, $d['hoan'], 2), '0.00'),
                'bu' => $ds->reduce(fn ($c, $d) => bcadd($c, $d['bu'], 2), '0.00'),
                'thuan' => $ds->reduce(fn ($c, $d) => bcadd($c, $d['thuan'], 2), '0.00'),
            ])
            ->sortKeys()
            ->values();
    }
}
