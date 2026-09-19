<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\RevenueReport;
use Illuminate\View\View;

/** Báo cáo doanh thu toàn thời gian: bảng số liệu và biểu đồ. */
class ReportController extends Controller
{
    public function index(RevenueReport $baoCao): View
    {
        return view('admin.reports.index', [
            'tongQuan' => $baoCao->tongQuan(),
            'danhMuc' => $baoCao->theoDanhMuc(),
            'theoNgay' => $baoCao->theoNgay()->reverse()->values(),
            'theoThang' => $baoCao->theoThang()->reverse()->values(),
            'theoNam' => $baoCao->theoNam()->reverse()->values(),
            'phuongThuc' => $baoCao->theoPhuongThuc(),
        ]);
    }

    public function charts(RevenueReport $baoCao): View
    {
        return view('admin.reports.charts', [
            'danhMuc' => $baoCao->theoDanhMuc(),
            'ngay' => $baoCao->bieuDoNgay(30),
            'thang' => $baoCao->bieuDoThang(12),
            'nam' => $baoCao->theoNam(),
            'phuongThuc' => $baoCao->theoPhuongThuc(),
        ]);
    }
}
