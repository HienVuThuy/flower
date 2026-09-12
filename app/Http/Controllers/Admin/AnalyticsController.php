<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserEventType;
use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\ReportExporter;
use App\Services\Analytics\ReportSections;
use App\Services\Shipping\GHNService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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

    public function index(Request $request, GHNService $ghn): View
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

            /*
             * SỐ LIỆU CHO BIỂU ĐỒ — theo ĐÚNG kỳ đang chọn.
             *
             * Khác khối `daily` ngay trên: khối đó cố định 14 ngày vì nó
             * trả lời một câu hỏi khác ("gần đây có ai dùng không").
             * Mấy khối dưới đây phải đi theo ô chọn kỳ, nếu không thì
             * biểu đồ và bảng số cạnh nó nói về hai khoảng thời gian
             * khác nhau mà không có gì báo.
             */
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

    /**
     * Trang CHỌN phần muốn xuất và định dạng.
     * ============================================================
     * VÌ SAO CÓ MỘT BƯỚC CHỌN, thay vì một nút tải thẳng:
     *
     * Bản trước xuất một tệp CỐ ĐỊNH gồm năm phần. Ai chỉ cần bảng bán
     * chạy vẫn phải tải cả tệp rồi tự xoá bốn phần thừa; ai cần bảng
     * khách hàng thì không có cách nào lấy.
     *
     * Danh sách phần lấy từ ReportSections — cùng một nơi mà đoạn ghi
     * tệp đọc. Khai ở hai chỗ thì ô đánh dấu có phần mà tệp không có.
     */
    public function exportForm(Request $request): View
    {
        return view('admin.analytics.export', [
            'period' => $this->period($request),
            'periods' => AnalyticsService::PERIODS,
            'sections' => ReportSections::danhSach(),
            'formats' => ReportExporter::DINH_DANG,
        ]);
    }

    /**
     * Ghi tệp theo đúng những gì admin vừa chọn.
     *
     * KIỂM LẠI MỌI THỨ GỬI LÊN. `phan` là mảng mã đến từ trình duyệt —
     * lọc qua danh sách hợp lệ chứ không đưa thẳng vào bộ dựng bảng.
     */
    public function export(Request $request, ReportSections $sections, ReportExporter $exporter): Response
    {
        $period = $this->period($request);
        $this->analytics->forPeriod($period);

        $dinhDang = (string) $request->input('dinh_dang', 'csv');

        if (! array_key_exists($dinhDang, ReportExporter::DINH_DANG)) {
            $dinhDang = 'csv';
        }

        $chon = array_values(array_intersect(
            array_map('strval', (array) $request->input('phan', [])),
            ReportSections::maHopLe(),
        ));

        /*
         * KHÔNG CHỌN GÌ THÌ XUẤT TẤT CẢ.
         *
         * Trả về một tệp rỗng là đúng chữ nhưng vô dụng: người dùng bấm
         * "Tải về", nhận một tệp không có gì, và không biết mình đã quên
         * bước nào. Giao diện cũng đã tích sẵn tất cả.
         */
        if ($chon === []) {
            $chon = ReportSections::maHopLe();
        }

        return $exporter->xuat(
            $dinhDang,
            $sections->nhieuBang($chon),
            AnalyticsService::PERIODS[$period],
        );
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
        return AnalyticsService::hopLeKy($request->query('ky'));
    }
}
