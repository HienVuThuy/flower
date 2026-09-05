<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InquiryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\BulkOrderInquiry;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'categories' => Category::count(),
            'products' => Product::count(),
            'customers' => User::where('role', UserRole::Customer)->count(),
            'pending_inquiries' => BulkOrderInquiry::where('status', InquiryStatus::New)->count(),
        ];

        $topViewedProducts = Product::query()
            ->orderByDesc('view_count')
            ->take(5)
            ->get(['id', 'name', 'view_count', 'slug']);

        $recentInquiries = BulkOrderInquiry::query()
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', [
            'todo' => $this->todo(),
            'stats' => $stats,
            'topViewedProducts' => $topViewedProducts,
            'recentInquiries' => $recentInquiries,
            'orderStats' => $this->orderStats(),
            'recentOrders' => $this->recentOrders(),
        ]);
    }

    /**
     * Số liệu đơn hàng.
     *
     * Doanh thu CHỈ tính đơn đã giao xong. Đơn đang chờ chưa chắc thành
     * tiền, còn đơn đã huỷ thì không bao giờ — cộng chúng vào là báo cáo
     * sai. Trả về null khi module giỏ hàng đang tắt, để view biết mà nói
     * rõ thay vì hiện một dãy số 0 gây hiểu nhầm.
     *
     * @return array<string, mixed>|null
     */
    /**
     * VIỆC CẦN LÀM HÔM NAY — khối đầu tiên của trang.
     * ============================================================
     * Bản trước, thứ đầu tiên admin nhìn thấy khi mở trang quản trị là
     * bốn con số: bao nhiêu danh mục, bao nhiêu sản phẩm, bao nhiêu
     * khách, bao nhiêu yêu cầu. Không con số nào trong đó nói cho họ
     * biết PHẢI LÀM GÌ — chúng gần như không đổi từ ngày này sang ngày
     * khác, và người ta học cách lướt qua.
     *
     * Trang quản trị của một cửa hàng thật mở đầu bằng HÀNG ĐỢI VIỆC:
     * đơn chờ xác nhận, hàng sắp hết, tiền còn nợ khách. Bốn mục dưới
     * đây đều là thứ có người phải động tay, và mỗi mục dẫn thẳng tới
     * đúng danh sách đã lọc sẵn.
     *
     * ĐẾM BẰNG TRUY VẤN RIÊNG, không nạp cả bảng rồi count() trong PHP:
     * bốn câu COUNT rẻ hơn nhiều so với kéo vài nghìn dòng lên bộ nhớ.
     *
     * @return list<array{label: string, count: int, url: string, tone: string}>
     */
    private function todo(): array
    {
        /*
         * ĐƠN ĐÃ HUỶ MÀ KHÁCH ĐÃ TRẢ TIỀN = CỬA HÀNG ĐANG NỢ KHÁCH.
         *
         * Đặt đầu danh sách vì đây là việc duy nhất trong bốn mục liên
         * quan tới tiền của người khác. Trước đây không có chỗ nào hiện
         * nó ra — admin chỉ phát hiện khi tình cờ mở đúng đơn đó.
         */
        $canHoanTien = Order::query()
            ->where('status', OrderStatus::Cancelled)
            ->where('payment_status', PaymentStatus::Paid)
            ->count();

        $choXacNhan = Order::where('status', OrderStatus::Pending)->count();

        /*
         * Chỉ tính hàng CÓ QUẢN LÝ TỒN KHO.
         *
         * Hoa cưới và hoa sự kiện có stock_quantity = 0 nhưng làm theo
         * đơn, không hề hết hàng. Gộp vào là con số cảnh báo lúc nào
         * cũng khác không và admin học cách bỏ qua nó.
         */
        $hetHang = Product::query()
            ->where('track_inventory', true)
            ->where('stock_quantity', '<=', 0)
            ->count();

        $yeuCauMoi = BulkOrderInquiry::where('status', InquiryStatus::New)->count();

        $viec = [
            [
                'label' => 'đơn đã huỷ cần hoàn tiền cho khách',
                'count' => $canHoanTien,
                'url' => route('admin.orders.index', ['status' => 'cancelled', 'payment' => 'paid']),
                'tone' => 'danger',
            ],
            [
                'label' => 'đơn chờ xác nhận',
                'count' => $choXacNhan,
                'url' => route('admin.orders.index', ['status' => 'pending']),
                'tone' => 'warning',
            ],
            [
                'label' => 'sản phẩm đã hết hàng',
                'count' => $hetHang,
                'url' => route('admin.products.index', ['kho' => 'het']),
                'tone' => 'warning',
            ],
            [
                'label' => 'yêu cầu báo giá chưa xử lý',
                'count' => $yeuCauMoi,
                'url' => route('admin.bulk-inquiries.index', ['status' => InquiryStatus::New->value]),
                'tone' => 'info',
            ],
        ];

        /*
         * BỎ MỤC CÓ SỐ 0.
         *
         * Danh sách toàn "0 đơn chờ xác nhận" là danh sách không ai đọc.
         * Hết việc thì nói hết việc — xem nhánh @empty ở view.
         */
        return array_values(array_filter($viec, fn (array $v) => $v['count'] > 0));
    }

    private function orderStats(): ?array
    {
        if (! config('features.cart')) {
            return null;
        }

        return [
            'total' => Order::count(),
            'open' => Order::open()->count(),
            'pending' => Order::where('status', OrderStatus::Pending)->count(),
            'completed' => Order::where('status', OrderStatus::Completed)->count(),
            'revenue' => (float) Order::where('status', OrderStatus::Completed)->sum('grand_total'),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, Order> */
    private function recentOrders()
    {
        if (! config('features.cart')) {
            return collect();
        }

        return Order::query()->latest()->take(5)->get();
    }
}
