<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AbandonedCarts;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\ChonKy;
use App\Services\Analytics\ProfitReport;
use App\Services\Analytics\PurchasingReport;
use App\Services\Analytics\ReviewReport;
use App\Services\Analytics\SalesBreakdown;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Các trang con của Phân tích: Doanh thu, Khách hàng, Đánh giá, Lợi nhuận. */
class AnalyticsPagesController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
    ) {
    }

    public function sales(Request $request, SalesBreakdown $bao): View
    {
        [$ky, $khoang] = $this->ky($request);

        $bao->trong($khoang);

        return view('admin.analytics.sales', $this->chung($ky) + [
            'danhMuc' => $bao->theoDanhMuc(),
            'tinh' => $bao->theoTinh(),
            'khungGio' => $bao->theoKhungGio(),
        ]);
    }

    public function customers(Request $request, SalesBreakdown $bao, AbandonedCarts $gio): View
    {
        [$ky, $khoang] = $this->ky($request);

        return view('admin.analytics.customers', $this->chung($ky) + [
            'khach' => $bao->trong($khoang)->khachMoiVaQuayLai(),
            'gioBoDo' => $gio->baoCao(),
        ]);
    }

    public function reviews(Request $request, ReviewReport $bao): View
    {
        [$ky, $khoang] = $this->ky($request);

        $bao->trong($khoang);

        return view('admin.analytics.reviews', $this->chung($ky) + [
            'tongQuan' => $bao->tongQuan(),
            'biChe' => $bao->sanPhamBiCheNhieu(),
            'theoThang' => $bao->theoThang(),
        ]);
    }

    public function profit(Request $request, ProfitReport $bao): View
    {
        [$ky, $khoang] = $this->ky($request);

        return view('admin.analytics.profit', $this->chung($ky) + [
            'loi' => $bao->trong($khoang)->baoCao(),
            'buShip' => $this->analytics->shippingCost(),
            'chiPhi' => \App\Services\Analytics\CashFlowReport::chiPhi($khoang),

            'hoa' => app(\App\Services\Analytics\FlowerCostReport::class)->trong($khoang)->baoCao(),
        ]);
    }

    public function purchasing(Request $request, PurchasingReport $bao): View
    {
        [$ky, $khoang] = $this->ky($request);

        $bao->trong($khoang);

        return view('admin.analytics.purchasing', $this->chung($ky) + [
            'hoa' => $bao->hoa(),
            'hang' => $bao->hang(),
            'thieuNguon' => $bao->thieuNguon(),
        ]);
    }

    private function ky(Request $request): array
    {
        $ky = ChonKy::tuRequest($request);

        return [$ky, $ky->apDung($this->analytics)->khoang()];
    }

    private function chung(ChonKy $ky): array
    {
        return [
            'ky' => $ky,
            'period' => $ky->ma,
            'periods' => AnalyticsService::PERIODS,
        ];
    }
}
