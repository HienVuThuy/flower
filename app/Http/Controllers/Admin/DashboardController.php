<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Admin\WorkQueue;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\ChonKy;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Trang Tổng quan của quản trị. */
class DashboardController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly WorkQueue $queue,
    ) {
    }

    public function index(Request $request): View
    {
        $ky = ChonKy::tuRequest($request);
        $period = $ky->ma;

        $ky->apDung($this->analytics);

        $orderStats = $this->analytics->orderStats();

        $previous = null;

        if ($this->analytics->forPreviousPeriod($period)) {
            $previous = $this->analytics->orderStats();
        }

        $ky->apDung($this->analytics);

        return view('admin.dashboard', [
            'todo' => $this->queue->items(),

            'ky' => $ky,
            'period' => $period,
            'periods' => AnalyticsService::PERIODS,

            'orderStats' => $orderStats,
            'previous' => $previous,

            'recentOrders' => $this->recentOrders(),
        ] + $this->soLieuNghiepVu($request));
    }

    private function soLieuNghiepVu(Request $request): array
    {
        $nguoi = $request->user();
        $khoang = $this->analytics->khoang();
        $ra = [];

        $hoa = ($nguoi?->can('kho') || $nguoi?->can('tai-chinh'))
            ? app(\App\Services\Analytics\FlowerCostReport::class)->trong($khoang)->baoCao()
            : null;

        if ($nguoi?->can('kho')) {

            $traNcc = \App\Models\StockReceipt::query()
                ->where('kind', \App\Enums\StockReceiptKind::TraNcc->value)
                ->where('status', \App\Enums\StockReceiptStatus::Posted->value);
            $khoang->apDungNgay($traNcc, 'received_at');

            $loTra = \App\Models\FlowerLot::query()->whereNotNull('tra_lai_at');
            $khoang->apDung($loTra, 'tra_lai_at');

            $ra['kho'] = [
                'thu_mua' => app(\App\Services\Analytics\PurchasingReport::class)->trong($khoang)->tongQuan(),
                'ton' => app(\App\Services\Analytics\InventoryReport::class)->trongVong(30)->tongQuan(),
                'lo_mo' => $hoa['so_lo_con_mo'],
                'tien_lo_mo' => $hoa['tien_lo_con_mo'],
                'lo_qua_han' => $hoa['lo_qua_han'],
                'tra_ncc' => $traNcc->count() + $loTra->count(),
            ];
        }

        if ($nguoi?->can('don-hang')) {
            $doi = \App\Models\Exchange::query()->where('status', '!=', \App\Enums\ExchangeStatus::Huy->value);
            $khoang->apDung($doi, 'created_at');

            $ra['doi_hang'] = $doi->count();
        }

        if ($nguoi?->can('tai-chinh')) {
            $ra['lai'] = [
                'hang' => app(\App\Services\Analytics\ProfitReport::class)->trong($khoang)->baoCao(),
                'hoa' => $hoa['lai_gop'],
            ];
        }

        return ['nghiepVu' => $ra];
    }

    private function recentOrders()
    {
        if (! config('features.cart')) {
            return collect();
        }

        return Order::query()->latest()->take(6)->get();
    }
}
