<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserEventType;
use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Trang Phân tích của admin (Guide §11 — "Admin → Analytics").
 * ============================================================
 * Controller mỏng có chủ đích: mọi truy vấn nằm ở AnalyticsService.
 * Nhờ vậy khi Bảng điều khiển cần cùng một chỉ số, nó gọi cùng một hàm
 * thay vì tự viết lại — và hai màn hình không bao giờ nói hai con số
 * khác nhau cho cùng một câu hỏi.
 */
class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
    ) {
    }

    public function index(Request $request): View
    {
        $period = $this->period($request);

        $this->analytics->forPeriod($period);

        /*
         * SO SÁNH VỚI KỲ TRƯỚC.
         *
         * Phải lấy số của KỲ NÀY trước, vì forPreviousPeriod() dời cửa sổ
         * thời gian của chính đối tượng service — mọi truy vấn sau đó sẽ
         * trả về số của kỳ trước. Đọc xong kỳ trước thì dời lại về kỳ này
         * cho các khối còn lại của trang.
         */
        $funnel = $this->analytics->funnel();
        $orderStats = $this->analytics->orderStats();

        $previous = null;

        if ($this->analytics->forPreviousPeriod($period)) {
            $previous = [
                'funnel' => $this->analytics->funnel(),
                'orders' => $this->analytics->orderStats(),
            ];
        }

        $this->analytics->forPeriod($period);

        return view('admin.analytics.index', [
            'previous' => $previous,
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

            // Biểu đồ luôn hiện 14 ngày gần nhất, không đổi theo ô chọn:
            // nó trả lời "gần đây có ai dùng không", khác câu hỏi của
            // các khối còn lại.
            'daily' => $this->analytics->dailyActivity(),
        ]);
    }

    /**
     * Xuất báo cáo ra CSV.
     * ============================================================
     * VÌ SAO CSV CHỨ KHÔNG PHẢI XLSX: xuất .xlsx cần thêm thư viện
     * (PhpSpreadsheet, ~40MB phụ thuộc) chỉ để làm đúng một việc. CSV là
     * văn bản thuần, Excel và Google Sheets đều mở trực tiếp, và sinh ra
     * bằng hàm có sẵn của PHP.
     *
     * STREAM chứ không dựng chuỗi trong bộ nhớ: hiện dữ liệu còn nhỏ,
     * nhưng bảng bán chạy sẽ dài ra theo thời gian, và một hàm xuất file
     * ngốn bộ nhớ tỉ lệ thuận với dữ liệu là quả bom hẹn giờ.
     */
    public function export(Request $request): StreamedResponse
    {
        $period = $this->period($request);
        $this->analytics->forPeriod($period);

        $funnel = $this->analytics->funnel();
        $orders = $this->analytics->orderStats();
        $topViewed = $this->analytics->topProducts(UserEventType::ProductView, 20);
        $bestSellers = $this->analytics->bestSellers(20);
        $searches = $this->analytics->topSearches(20);

        $label = AnalyticsService::PERIODS[$period];
        $filename = 'phan-tich-'.$period.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($funnel, $orders, $topViewed, $bestSellers, $searches, $label) {
            $out = fopen('php://output', 'w');

            /*
             * BOM UTF-8 ở đầu tệp.
             *
             * Không có nó, Excel trên Windows đọc CSV theo bảng mã hệ
             * thống và mọi tên sản phẩm tiếng Việt thành ký tự rác. Ba
             * byte này là khác biệt giữa một tệp dùng được và một tệp
             * người nhận phải tự đi dò bảng mã.
             */
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Báo cáo phân tích', $label]);
            fputcsv($out, ['Xuất lúc', now()->format('H:i d/m/Y')]);
            fputcsv($out, []);

            fputcsv($out, ['PHỄU CHUYỂN ĐỔI (đếm theo phiên)']);
            fputcsv($out, ['Phiên có xem sản phẩm', $funnel['views']]);
            fputcsv($out, ['Phiên có thêm vào giỏ', $funnel['carts']]);
            fputcsv($out, ['Phiên có mua', $funnel['purchases']]);
            // null nghĩa là mẫu số bằng 0 — ghi "không tính được" chứ
            // không ghi 0, vì 0% và "chưa có dữ liệu" là hai chuyện khác.
            fputcsv($out, ['Xem → Giỏ (%)', $funnel['view_to_cart'] ?? 'không tính được']);
            fputcsv($out, ['Giỏ → Mua (%)', $funnel['cart_to_purchase'] ?? 'không tính được']);
            fputcsv($out, []);

            fputcsv($out, ['ĐƠN HÀNG']);
            fputcsv($out, ['Tổng đơn', $orders['total']]);
            fputcsv($out, ['Đã giao', $orders['completed']]);
            fputcsv($out, ['Đã huỷ', $orders['cancelled']]);
            fputcsv($out, ['Doanh thu (đơn đã giao)', $orders['revenue']]);
            fputcsv($out, ['Giá trị đơn trung bình', $orders['average'] ?? 'chưa có đơn đã giao']);
            fputcsv($out, []);

            fputcsv($out, ['SẢN PHẨM ĐƯỢC XEM NHIỀU']);
            fputcsv($out, ['Sản phẩm', 'Lượt xem']);

            foreach ($topViewed as $row) {
                // `product` là null khi sản phẩm đã bị xoá mà nhật ký còn
                // — ghi rõ như vậy thay vì để ô trống không giải thích.
                fputcsv($out, [$row['product']?->name ?? '(sản phẩm đã xoá)', $row['total']]);
            }

            fputcsv($out, []);
            fputcsv($out, ['SẢN PHẨM BÁN CHẠY (đơn đã giao)']);
            fputcsv($out, ['Sản phẩm', 'Số lượng', 'Doanh thu']);

            foreach ($bestSellers as $row) {
                fputcsv($out, [$row['name'], $row['quantity'], $row['revenue']]);
            }

            fputcsv($out, []);
            fputcsv($out, ['TỪ KHOÁ TÌM KIẾM']);
            fputcsv($out, ['Từ khoá', 'Lượt tìm']);

            foreach ($searches as $row) {
                fputcsv($out, [$row['term'], $row['total']]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Kỳ đang chọn, đã kiểm tra hợp lệ.
     *
     * Chỉ nhận đúng các khoảng đã khai. Tham số lạ trên URL rơi về '30'
     * thay vì được đưa thẳng vào truy vấn.
     *
     * Dùng chung cho cả trang xem và trang xuất file: nếu hai nơi tự
     * kiểm tra riêng thì tệp CSV có thể chứa dữ liệu của một kỳ khác với
     * kỳ admin đang nhìn trên màn hình.
     */
    private function period(Request $request): string
    {
        $period = (string) $request->query('ky', '30');

        return array_key_exists($period, AnalyticsService::PERIODS) ? $period : '30';
    }
}
