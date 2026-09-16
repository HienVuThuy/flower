<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Pricing\DemandSignals;
use App\Services\Pricing\PricingAdvisor;
use App\Services\Promotion\OccasionCalendar;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Trang "Đề xuất giá & ưu đãi" của admin. */
class PricingAdvisorController extends Controller
{
    public const WINDOWS = [
        7 => '7 ngày',
        30 => '30 ngày',
        90 => '90 ngày',
    ];

    public function index(Request $request, OccasionCalendar $lich): View
    {
        $ngay = (int) $request->query('ngay', 30);

        if (! array_key_exists($ngay, self::WINDOWS)) {
            $ngay = 30;
        }

        $ketQua = (new PricingAdvisor(new DemandSignals($ngay)))->suggest();

        return view('admin.pricing-advisor.index', [
            'result' => $ketQua,
            'window' => $ngay,
            'windows' => self::WINDOWS,
            'occasions' => $lich->upcoming(90),
            'lunarOccasions' => $lich->lunar(),
            'minViews' => (int) config('pricing-advisor.min_views'),
            'confidentOrders' => (int) config('pricing-advisor.confident_orders'),
        ]);
    }
}
