<?php

namespace App\Services\Admin;

use App\Enums\BoardingStatus;
use App\Enums\ExchangeStatus;
use App\Enums\FlowerLotStatus;
use App\Enums\InquiryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockCountStatus;
use App\Enums\StockReceiptStatus;
use App\Models\BoardingBooking;
use App\Models\BulkOrderInquiry;
use App\Models\CommunityPost;
use App\Models\Exchange;
use App\Models\Order;
use App\Models\Review;
use App\Models\StockCount;
use App\Models\StockReceipt;
use App\Services\Analytics\InventoryReport;
use App\Services\Inventory\FlowerLotService;

/** HÀNG ĐỢI VIỆC của trang quản trị. */
class WorkQueue
{
    public function __construct(
        private readonly InventoryReport $kho,
    ) {
    }

    public function items(): array
    {
        $tongQuanKho = $this->kho->trongVong(30)->tongQuan();

        $viec = [
            [
                'label' => 'đơn đã huỷ cần hoàn tiền cho khách',
                'count' => Order::query()
                    ->where('status', OrderStatus::Cancelled)
                    ->where('payment_status', PaymentStatus::Paid)
                    ->count(),
                'url' => route('admin.orders.index', ['status' => 'cancelled', 'payment' => 'paid']),
                'tone' => 'danger',
                'hint' => 'Khách đã trả tiền cho đơn không còn nữa. Hệ thống không tự chuyển tiền lại.',
            ],

            [
                'label' => 'đơn có khoản hoàn tiền MoMo chưa rõ kết quả',
                'count' => Order::refundPending()->count(),
                'url' => route('admin.orders.index', ['hoan_tien' => 'chua-ro']),
                'tone' => 'danger',
                'hint' => 'MoMo không trả lời lúc hoàn. Kiểm trên cổng MoMo rồi xác nhận; đừng hoàn lại lần nữa.',
            ],

            [
                'label' => 'đơn chờ xác nhận',
                'count' => Order::where('status', OrderStatus::Pending)->count(),
                'url' => route('admin.orders.index', ['status' => 'pending']),
                'tone' => 'warning',
                'hint' => 'Khách đã đặt và đang đợi cửa hàng nhận đơn.',
            ],

            [
                'label' => 'đơn đã nhận nhưng chưa có vận đơn',
                'count' => Order::awaitingWaybill()->count(),
                'url' => route('admin.orders.index', ['van_don' => 'cho-tao']),
                'tone' => 'warning',
                'hint' => 'Đơn COD không tự tạo vận đơn. Chưa tạo thì hàng chưa đi.',
            ],

            [
                'label' => 'mặt hàng đã hết nhưng vẫn đang bày bán',
                'count' => $tongQuanKho['out'],
                'url' => route('admin.inventory.index'),
                'tone' => 'warning',
                'hint' => 'Khách vẫn bấm vào được nhưng không mua được.',
            ],

            [
                'label' => 'mặt hàng còn dưới 14 ngày bán',
                'count' => $tongQuanKho['low'],
                'url' => route('admin.inventory.index'),
                'tone' => 'info',
                'hint' => 'Tính theo tốc độ bán 30 ngày qua, không theo số lượng còn lại.',
            ],

            [
                'label' => 'phiếu nhập còn nháp, chưa cộng vào kho',
                'count' => StockReceipt::where('status', StockReceiptStatus::Draft)->count(),
                'url' => route('admin.stock-receipts.index', ['trang-thai' => StockReceiptStatus::Draft->value]),
                'tone' => 'info',
                'hint' => 'Hàng đã nhận nhưng tồn kho chưa được cộng thêm.',
            ],

            [
                'label' => 'phiếu kiểm kê còn nháp, chưa điều chỉnh kho',
                'count' => StockCount::where('status', StockCountStatus::Draft)->count(),
                'url' => route('admin.stock-counts.index', ['trang-thai' => StockCountStatus::Draft->value]),
                'tone' => 'info',
                'hint' => 'Đã đếm hàng thật nhưng tồn trên hệ thống vẫn là số cũ.',
            ],

            [
                'label' => 'lô hoa mở quá ' . FlowerLotService::NGAY_NHAC_DONG . ' ngày, có thể đã dùng hết mà quên đóng',
                'count' => app(FlowerLotService::class)->loQuenDong()->count(),
                'url' => route('admin.flower-lots.index', ['trang_thai' => FlowerLotStatus::DangDung->value]),
                'tone' => 'warning',
                'hint' => 'Chưa đóng thì tiền lô chưa vào giá vốn — lãi gộp hoa đang cao hơn sự thật.',
            ],

            [
                'label' => 'phiếu đổi hàng đã nhận hàng trả nhưng chưa hoàn tất',
                'count' => Exchange::where('status', ExchangeStatus::DaNhan)->count(),
                'url' => route('admin.exchanges.index', ['trang_thai' => ExchangeStatus::DaNhan->value]),
                'tone' => 'warning',
                'hint' => 'Khách đã gửi hàng lại và đang đợi hàng đổi.',
            ],

            [
                'label' => 'phiếu đổi hàng đang chờ khách gửi hàng về',
                'count' => Exchange::where('status', ExchangeStatus::ChoNhan)->count(),
                'url' => route('admin.exchanges.index', ['trang_thai' => ExchangeStatus::ChoNhan->value]),
                'tone' => 'info',
                'hint' => 'Theo dõi để nhắc khách nếu để lâu.',
            ],

            [
                'label' => 'đánh giá 1-2 sao chưa được trả lời',
                'count' => Review::query()
                    ->where('rating', '<=', 2)
                    ->whereNull('admin_reply')
                    ->count(),
                'url' => route('admin.reviews.index', ['sao' => 'thap', 'tra_loi' => 'chua']),
                'tone' => 'info',
                'hint' => 'Lời phàn nàn đang hiển thị công khai mà cửa hàng chưa nói gì.',
            ],

            [
                'label' => 'bài cộng đồng chờ duyệt',
                'count' => CommunityPost::pending()->count(),
                'url' => route('admin.community.index'),
                'tone' => 'info',
                'hint' => 'Khách đã đăng nhưng chưa ai xem qua.',
            ],

            [
                'label' => 'yêu cầu báo giá chưa xử lý',
                'count' => BulkOrderInquiry::where('status', InquiryStatus::New)->count(),
                'url' => route('admin.bulk-inquiries.index', ['status' => InquiryStatus::New->value]),
                'tone' => 'info',
                'hint' => 'Khách hỏi mua số lượng lớn và đang đợi trả lời.',
            ],

            [
                'label' => 'yêu cầu chăm cây hộ chờ xác nhận',
                'count' => BoardingBooking::where('status', BoardingStatus::ChoDuyet)->count(),
                'url' => route('admin.boarding.index', ['trang_thai' => BoardingStatus::ChoDuyet->value]),
                'tone' => 'warning',
                'hint' => 'Khách đã gửi yêu cầu và đang đợi cửa hàng hẹn ngày nhận cây.',
            ],

            [
                'label' => 'yêu cầu thêm của khách gửi chăm hộ chờ báo giá',
                'count' => \App\Models\BoardingExtra::where('status', \App\Enums\BoardingExtraStatus::ChoBaoGia)->count(),
                'url' => route('admin.boarding.index'),
                'tone' => 'warning',
                'hint' => 'Khách muốn thêm việc (thay chậu, tạo dáng…) và đang đợi cửa hàng báo giá.',
            ],

            [
                'label' => 'việc làm thêm khách đã đồng ý, chưa làm',
                'count' => \App\Models\BoardingExtra::where('status', \App\Enums\BoardingExtraStatus::DaDongY)->count(),
                'url' => route('admin.boarding.index'),
                'tone' => 'info',
                'hint' => 'Làm xong thì bấm "Đã làm" và gửi ảnh cho khách.',
            ],

            [
                'label' => 'cây gửi chăm hộ sắp đến ngày trả',
                'count' => BoardingBooking::where('status', BoardingStatus::ChoTra)->count(),
                'url' => route('admin.boarding.index', ['trang_thai' => BoardingStatus::ChoTra->value]),
                'tone' => 'warning',
                'hint' => 'Chuẩn bị cây (tỉa, lau lá) và hẹn giờ giao lại cho khách.',
            ],

            [
                'label' => 'cây khách muốn gửi lặp lại nhưng chưa có lịch dịp năm sau',
                'count' => BoardingBooking::where('waiting_next_window', true)->count(),
                'url' => route('admin.boarding-windows.index'),
                'tone' => 'info',
                'hint' => 'Thêm ngày dịp năm sau (ví dụ Tết) là hệ thống tự mở phiếu kỳ mới.',
            ],
        ];

        $hotlineLuu = trim((string) \App\Services\Shop\StoreProfile::get('site_hotline'));

        $viec[] = [
            'label' => 'cấu hình cần sửa: hotline "' . \Illuminate\Support\Str::limit($hotlineLuu, 20) . '" không phải số điện thoại',
            'count' => ($hotlineLuu !== '' && \App\Services\Shop\StoreProfile::hotline() === null) ? 1 : 0,
            'url' => route('admin.settings.edit'),
            'tone' => 'info',
            'hint' => 'Email gửi khách và chân trang đang ẩn hotline vì không gọi được. Nhập số thật hoặc để trống.',
        ];

        return array_values(array_filter($viec, fn (array $v) => $v['count'] > 0));
    }
}
