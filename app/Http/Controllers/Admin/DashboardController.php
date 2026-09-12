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
        $revenueDaily = $this->analytics->revenueByDay();
        $statusMix = $this->analytics->statusBreakdown();
        $bestSellers = $this->analytics->bestSellers(5);

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
            'revenueDaily' => $revenueDaily,
            'statusMix' => $statusMix,
            'bestSellers' => $bestSellers,

            'recentOrders' => $this->recentOrders(),
        ]);
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
