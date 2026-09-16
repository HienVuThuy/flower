<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserEventType;
use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\ChonKy;
use App\Services\Analytics\ReportExporter;
use App\Services\Analytics\ReportSections;
use App\Services\Shipping\GHNService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Trang Phân tích của admin (Guide §11 — "Admin → Analytics"). */
class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
    ) {
    }

    public function index(Request $request, GHNService $ghn): View
    {
        $ky = ChonKy::tuRequest($request);
        $period = $ky->ma;

        $ky->apDung($this->analytics);

        $funnel = $this->analytics->funnel();
        $orderStats = $this->analytics->orderStats();

        $previous = null;

        if ($this->analytics->forPreviousPeriod($period)) {
            $previous = [
                'funnel' => $this->analytics->funnel(),
                'orders' => $this->analytics->orderStats(),
            ];
        }

        $ky->apDung($this->analytics);

        return view('admin.analytics.index', [
            'previous' => $previous,
            'ky' => $ky,
            'period' => $period,
            'periods' => AnalyticsService::PERIODS,

            'funnel' => $funnel,
            'totals' => $this->analytics->eventTotals(),
            'orderStats' => $orderStats,

            'topViewed' => $this->analytics->topProducts(UserEventType::ProductView),
            'topCarted' => $this->analytics->topProducts(UserEventType::AddToCart),
            'topWished' => $this->analytics->topProducts(UserEventType::Wishlist),

            'topCategories' => $this->analytics->topCategories(),
            'topSearches' => $this->analytics->topSearches(),
            'bestSellers' => $this->analytics->bestSellers(),

            'daily' => $this->analytics->dailyActivity(),

            'revenueDaily' => $this->analytics->revenueByDay(),
            'statusMix' => $this->analytics->statusBreakdown(),
            'paymentMix' => $this->analytics->paymentMix(),
            'topCustomers' => $this->analytics->topCustomers(),
            'couponUsage' => $this->analytics->couponUsage(),

            'shipping' => $this->analytics->shippingCost(),
            'shippingMonths' => $this->analytics->shippingCostByMonth(),
            'shippingSubsidies' => $this->analytics->shippingSubsidies(),
            'ghnSandbox' => $ghn->isSandbox(),
        ]);
    }

    public function exportForm(Request $request): View
    {
        $ky = ChonKy::tuRequest($request);

        return view('admin.analytics.export', [
            'ky' => $ky,
            'period' => $ky->ma,
            'periods' => AnalyticsService::PERIODS,
            'sections' => ReportSections::danhSach(),
            'formats' => ReportExporter::DINH_DANG,
        ]);
    }

    public function export(Request $request, ReportSections $sections, ReportExporter $exporter): Response
    {
        $ky = ChonKy::tuRequest($request);
        $ky->apDung($this->analytics);

        $dinhDang = (string) $request->input('dinh_dang', 'csv');

        if (! array_key_exists($dinhDang, ReportExporter::DINH_DANG)) {
            $dinhDang = 'csv';
        }

        $chon = array_values(array_intersect(
            array_map('strval', (array) $request->input('phan', [])),
            ReportSections::maHopLe(),
        ));

        if ($chon === []) {
            $chon = ReportSections::maHopLe();
        }

        return $exporter->xuat(
            $dinhDang,
            $sections->nhieuBang($chon),
            $ky->nhan(),
        );
    }

    private function period(Request $request): string
    {
        return AnalyticsService::hopLeKy($request->query('ky'));
    }
}
