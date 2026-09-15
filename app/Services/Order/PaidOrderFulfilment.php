<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Shipping\GHNOrderService;
use Illuminate\Support\Facades\Log;

/**
 * Việc phải làm ngay khi đơn đã được trả ĐỦ tiền: xác nhận, rồi bàn giao GHN.
 * ============================================================
 * Tách khỏi MomoController để trả góp dùng lại đúng đường này khi kỳ cuối
 * được trả (qua MoMo hay tại cửa hàng) — hai bản sao thì bản sau quên một
 * bước.
 *
 * VÌ SAO ĐƠN ĐÃ TRẢ TIỀN THÌ TỰ ĐỘNG, CÒN COD THÌ KHÔNG.
 *
 * Tạo vận đơn là CAM KẾT với GHN: họ cử người tới lấy hàng và tính tiền
 * cửa hàng. Với đơn COD, thứ duy nhất đứng sau lời hứa của khách là lời
 * hứa đó — nên cửa hàng phải nhìn đơn trước khi cam kết.
 *
 * Đơn đã trả tiền thì khác hẳn: khách đã bỏ tiền ra, và bắt họ đợi một
 * nhân viên bấm nút là kéo dài thời gian giao hàng vì một bước không còn
 * tác dụng gì. Vì thế xác nhận và bàn giao ngay.
 *
 * KHÔNG BAO GIỜ NÉM LỖI RA NGOÀI. Tiền đã ghi nhận xong rồi; một cuộc gọi
 * GHN hỏng không được phép biến thành trang lỗi trước mặt khách vừa trả
 * tiền. Hỏng thì ghi log, và nút tạo vận đơn thủ công ở trang quản trị vẫn
 * còn nguyên.
 */
class PaidOrderFulfilment
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly GHNOrderService $ghn,
    ) {
    }

    public function sauKhiTraTien(Order $order, string $ghiChu): void
    {
        // Đơn đã bị huỷ trước khi tiền về: không xác nhận, không bàn
        // giao. Trường hợp này cần người thật xử lý hoàn tiền.
        if ($order->status === OrderStatus::Cancelled) {
            return;
        }

        // Đơn trả góp chưa trả đủ: tiền của MỘT kỳ không phải lời cam kết giao hàng.
        if ($order->choDoiTraGop()) {
            return;
        }

        if ($order->status === OrderStatus::Pending) {
            try {
                $this->orders->changeStatus($order, OrderStatus::Confirmed, $ghiChu, tuDong: true);
            } catch (OrderException $e) {
                Log::warning('Không tự xác nhận được đơn sau thanh toán.', [
                    'order' => $order->order_number,
                    'ly_do' => $e->getMessage(),
                ]);
            }
        }

        /*
         * KHÔNG kiểm "đã có vận đơn chưa" ở đây.
         *
         * GHNOrderService::create() đã tự chặn: có mã rồi thì nó trả về
         * ngay, không gọi ra GHN. Kiểm lại lần nữa ở đây là dựng bản thứ
         * hai của cùng một luật — và bản thứ hai sẽ lệch vào đúng ngày ai
         * đó sửa bản thứ nhất.
         *
         * Đã kiểm bằng cách bỏ chốt cũ đi: bài kiểm thử đếm số lời gọi
         * GHN vẫn xanh, tức là lớp dưới thật sự đang gánh việc đó.
         */
        try {
            $ketQua = $this->ghn->create($order->refresh());

            if (($ketQua['code'] ?? null) !== 200) {
                Log::error('Không tạo được vận đơn GHN sau thanh toán.', [
                    'order' => $order->order_number,
                    'ghn' => $ketQua['message'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Lỗi khi tạo vận đơn GHN sau thanh toán.', [
                'order' => $order->order_number,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
