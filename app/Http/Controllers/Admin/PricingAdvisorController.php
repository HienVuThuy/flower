<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Pricing\DemandSignals;
use App\Services\Pricing\PricingAdvisor;
use App\Services\Promotion\OccasionCalendar;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Trang "Đề xuất giá & ưu đãi" của admin.
 * ============================================================
 * Controller mỏng có chủ đích, cùng lý do như `AnalyticsController`: mọi
 * phép đếm nằm ở `DemandSignals`, mọi phán đoán nằm ở `PricingAdvisor`,
 * mọi việc đối chiếu lịch nằm ở `OccasionCalendar`. Ở đây chỉ nhận tham
 * số và giao việc.
 */
class PricingAdvisorController extends Controller
{
    /**
     * Các cửa sổ quan sát cho admin chọn.
     *
     * KHÔNG cho nhập số ngày tuỳ ý qua URL: mỗi cửa sổ là một truy vấn
     * quét bảng sự kiện, và một tham số mở là lời mời cho ai đó gõ 99999
     * rồi trang treo.
     */
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
