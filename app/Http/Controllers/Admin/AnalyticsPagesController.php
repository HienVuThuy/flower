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

/**
 * Các trang con của Phân tích: Doanh thu, Khách hàng, Đánh giá, Lợi nhuận.
 * ============================================================
 * VÌ SAO TÁCH TRANG: trang Phân tích đã dài 638 dòng. Nhồi thêm bốn nhóm
 * báo cáo vào là một trang chạy hàng chục truy vấn cho mỗi lần mở, và người
 * cần xem "tỉnh nào mua nhiều" phải cuộn qua phễu chuyển đổi để tới.
 *
 * Mỗi trang chỉ chạy truy vấn của chính nó. Kỳ lấy từ
 * AnalyticsService::khoang() — cùng một định nghĩa "7 ngày qua" với trang
 * Tổng hợp.
 */
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

            /*
             * HOA TƯƠI CÓ BÁO CÁO RIÊNG, ở mức KỲ.
             *
             * Không ai biết bó hoa bán hôm qua dùng cành của lô nào, nên
             * hoa không ghép giá vốn vào từng dòng đơn được. Gộp nó vào
             * bảng lãi theo sản phẩm là bịa; để nó nằm im trong phần
             * "chưa có giá vốn" thì mất luôn con số lãi của mảng chiếm
             * phần lớn doanh thu một cửa hàng hoa.
             */
            'hoa' => app(\App\Services\Analytics\FlowerCostReport::class)->trong($khoang)->baoCao(),
        ]);
    }

    /**
     * Thu mua: lấy hàng ở đâu thì đáng tiền nhất.
     *
     * ============================================================
     * TRANG NÀY THUỘC QUYỀN `kho`, KHÔNG PHẢI `bao-cao`.
     *
     * Người trả lời câu "kỳ sau lấy hoa ở đâu" là người đi lấy hàng, và
     * người đó đã thấy giá nhập ở biểu mẫu nhập kho rồi — giấu bảng so
     * giá với chính họ thì bảng này không tới được tay ai dùng nó.
     *
     * Trang cũng KHÔNG có giá bán và không có lãi: nó chỉ nói về tiền
     * bỏ ra, nên không mở thêm gì mà `kho` chưa thấy.
     */
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

    /** @return array{0: ChonKy, 1: \App\Services\Analytics\KhoangThoiGian} */
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
