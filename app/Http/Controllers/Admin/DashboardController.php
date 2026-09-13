<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Admin\WorkQueue;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\ChonKy;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Trang Tổng quan của quản trị.
 * ============================================================
 * TRẢ LỜI ĐÚNG HAI CÂU, theo đúng thứ tự này:
 *
 *   1. HÔM NAY PHẢI LÀM GÌ?      → hàng đợi việc (WorkQueue)
 *   2. CỬA HÀNG ĐANG ĐI LÊN HAY ĐI XUỐNG? → mấy chỉ số kèm kỳ trước
 *
 * Bản trước mở đầu bằng bốn con số đếm: bao nhiêu danh mục, bao nhiêu
 * sản phẩm, bao nhiêu khách hàng. Chúng không trả lời câu nào trong hai
 * câu trên — gần như không đổi từ ngày này sang ngày khác, và người ta
 * học cách lướt qua cả khối.
 *
 * ============================================================
 * MỌI CON SỐ TIỀN VÀ ĐƠN ĐỀU ĐỌC TỪ AnalyticsService.
 *
 * Bản trước controller này TỰ VIẾT LẤY một hàm orderStats() riêng, với
 * định nghĩa doanh thu của riêng nó. Hai định nghĩa cho cùng một chỉ số
 * ở hai màn hình là chuyện chỉ chờ ngày lệch nhau: sửa cách tính ở
 * trang Phân tích thì trang này vẫn nói con số cũ, và không có gì trên
 * màn hình cho thấy hai nơi đang bất đồng. Người đọc tin cả hai.
 *
 * Controller này vì thế MỎNG có chủ đích: nó chọn kỳ, gọi service, và
 * đưa dữ liệu sang view. Không có phép tính nào ở đây.
 */
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

        /*
         * ĐỌC SỐ CỦA KỲ NÀY TRƯỚC.
         *
         * forPreviousPeriod() dời cửa sổ thời gian của CHÍNH đối tượng
         * service; mọi truy vấn sau lời gọi đó trả về số của kỳ trước.
         * Đọc nhầm thứ tự thì cả trang hiện số của kỳ trước mà vẫn ghi
         * nhãn là kỳ này — sai mà nhìn hoàn toàn bình thường.
         */
        $orderStats = $this->analytics->orderStats();

        // Biểu đồ doanh thu, trạng thái đơn, bán chạy nay chỉ ở trang Phân
        // tích — không chạy ba truy vấn đó cho một trang không vẽ chúng.

        /*
         * Kỳ 'Toàn bộ' KHÔNG có kỳ trước — không có gì nằm trước "toàn
         * bộ". Lúc đó $previous là null và view tự ẩn phần so sánh, thay
         * vì bịa ra một mốc để có cái mà so.
         */
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

    /**
     * Số liệu từ các nghiệp vụ sau bán hàng: kho, thu mua, hoa, đổi trả, lãi.
     * ============================================================
     * KHÔNG TÍNH LẠI Ở ĐÂY. Mỗi con số đọc từ đúng báo cáo đang phục vụ
     * trang riêng của nó (PurchasingReport, InventoryReport,
     * FlowerCostReport, ProfitReport) — Tổng quan và trang chi tiết không
     * được nói hai con số khác nhau cho cùng một câu hỏi.
     *
     * CHỈ CHẠY PHẦN NGƯỜI XEM CÓ QUYỀN. Nhân viên không có quyền tài chính
     * thì không thấy lãi — và cũng không tốn truy vấn tính lãi cho họ.
     */
    private function soLieuNghiepVu(Request $request): array
    {
        $nguoi = $request->user();
        $khoang = $this->analytics->khoang();
        $ra = [];

        // Báo cáo hoa dùng cho cả nhóm Kho lẫn ô Lãi gộp hoa — tính MỘT lần.
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

    /**
     * Mấy đơn mới nhất — KHÔNG theo kỳ đang chọn.
     *
     * Khối này trả lời "vừa có gì xảy ra", một câu hỏi khác hẳn với
     * "kỳ này bán được bao nhiêu". Cắt nó theo kỳ thì chọn "7 ngày qua"
     * ở một cửa hàng vắng khách sẽ cho ra một khối rỗng, trong khi câu
     * trả lời đúng vẫn tồn tại: đơn gần nhất là đơn tuần trước.
     *
     * @return \Illuminate\Support\Collection<int, Order>
     */
    private function recentOrders()
    {
        if (! config('features.cart')) {
            return collect();
        }

        return Order::query()->latest()->take(6)->get();
    }
}
