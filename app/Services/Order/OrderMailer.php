<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderCancelledByCustomerMail;
use App\Mail\OrderStatusMail;
use App\Models\Order;
use App\Models\Setting;
use App\Services\Mail\MailTransport;
use Illuminate\Support\Facades\Log;

/**
 * Gửi email xác nhận đơn hàng.
 * ============================================================
 * HAI NGUYÊN TẮC:
 *
 * 1. GỬI MAIL HỎNG KHÔNG ĐƯỢC LÀM HỎNG ĐƠN.
 *    Lúc gọi tới đây, đơn đã ghi vào cơ sở dữ liệu và kho đã trừ. Ném
 *    lỗi ra ngoài sẽ cho khách xem trang lỗi trong khi đơn thật sự đã
 *    được tạo — khách đặt lại, cửa hàng có hai đơn trùng.
 *
 * 2. KHÔNG NÓI DỐI LÀ ĐÃ GỬI.
 *    Dự án đang chạy MAIL_MAILER=log: thư chỉ được ghi vào tệp log,
 *    không tới hộp thư ai cả. deliversForReal() cho tầng giao diện biết
 *    sự thật để không hiện câu "đã gửi xác nhận tới email của bạn".
 */
class OrderMailer
{
    public function __construct(
        private readonly MailTransport $transport,
    ) {
    }

    /**
     * Hệ thống có thực sự gửi được thư tới hộp thư khách hay không.
     *
     * Logic nằm ở App\Services\Mail\MailTransport vì thư đặt lại mật
     * khẩu cũng cần đúng câu trả lời này. Chép sang nơi thứ hai thì hai
     * bản sẽ lệch nhau, và lệch ở đây nghĩa là một trong hai chỗ bắt đầu
     * nói dối người dùng.
     */
    public function deliversForReal(): bool
    {
        return $this->transport->deliversForReal();
    }

    /**
     * Thư xác nhận đơn — BIÊN NHẬN ĐẦY ĐỦ, kèm danh sách hàng và tổng tiền.
     *
     * TRƯỚC ĐÂY gửi ngay lúc khách bấm đặt hàng, khi đơn còn ở trạng
     * thái "Chờ xác nhận" — tức là gửi một lá thư tên "Xác nhận đơn
     * hàng" trước khi có ai ở cửa hàng xác nhận điều gì.
     *
     * NAY chỉ gửi khi đơn chuyển sang "Đã xác nhận". Không gọi trực
     * tiếp từ ngoài: sendStatusUpdate() tự chọn đúng lá thư cho từng
     * trạng thái, nên chỉ có MỘT nơi biết luật đó.
     *
     * @return bool đã bàn giao cho tầng mail hay chưa (không phải đã tới hộp thư)
     */
    private function sendConfirmation(Order $order): bool
    {
        if (! $order->recipient_email) {
            return false;
        }

        try {
            $this->transport->deliver(new OrderConfirmationMail($order), $order->recipient_email);

            return true;
        } catch (\Throwable $e) {
            Log::error('Không gửi được email xác nhận đơn hàng.', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Báo cho khách biết đơn vừa đổi trạng thái.
     *
     * Tự bỏ qua khi trạng thái không thuộc nhóm đáng báo (xem
     * OrderStatus::notifiesCustomer) — nơi gọi không phải tự kiểm tra,
     * nên không có chỗ nào quên mất quy tắc đó.
     *
     * @return bool đã bàn giao cho tầng mail hay chưa
     */
    public function sendStatusUpdate(Order $order): bool
    {
        if (! $order->status->notifiesCustomer() || ! $order->recipient_email) {
            return false;
        }

        /*
         * Khách đã tắt nhận thư loại này thì thôi.
         *
         * CHỈ áp dụng cho thư đổi trạng thái. Thư xác nhận đơn là biên
         * nhận mua hàng nên vẫn gửi, và thư bảo mật thì càng không được
         * tắt — xem migration 2026_09_01_010000.
         *
         * `$order->user` có thể null khi khách đặt mà không đăng nhập.
         * Khách vãng lai không có tài khoản nên cũng không có nơi nào để
         * bày tỏ ý muốn; mặc định là vẫn gửi, vì đó là cách duy nhất họ
         * biết đơn của mình đi tới đâu.
         */
        if ($order->user && ! $order->user->wantsOrderUpdates()) {
            return false;
        }

        /*
         * MỖI TRẠNG THÁI MỘT LÁ THƯ ĐÚNG VỚI NÓ.
         *
         * "Đã xác nhận" là lúc cửa hàng chốt đơn — khách cần một BIÊN
         * NHẬN đầy đủ để đối chiếu: đủ mặt hàng, đủ số lượng, đủ tổng
         * tiền, đủ địa chỉ giao. OrderConfirmationMail viết cho đúng
         * việc đó.
         *
         * Ba trạng thái sau ("Đang giao", "Đã giao", "Đã huỷ") là tin
         * cập nhật ngắn, không cần lặp lại toàn bộ biên nhận.
         *
         * Chọn ở ĐÂY chứ không ở nơi gọi: OrderService chỉ nói "đơn vừa
         * đổi trạng thái", còn việc trạng thái nào xứng lá thư nào là
         * luật của tầng gửi thư. Để nơi gọi tự chọn là mở đường cho một
         * nơi gọi thứ hai chọn khác đi.
         */
        $mail = $order->status === OrderStatus::Confirmed
            ? null
            : new OrderStatusMail($order->loadMissing('items'));

        if ($mail === null) {
            return $this->sendConfirmation($order);
        }

        try {
            $this->transport->deliver($mail, $order->recipient_email);

            return true;
        } catch (\Throwable $e) {
            /*
             * Nuốt lỗi có chủ đích, cùng lý do với sendConfirmation:
             * lúc gọi tới đây trạng thái đã ghi vào cơ sở dữ liệu và kho
             * đã hoàn (nếu huỷ). Ném lỗi ra sẽ cho admin xem trang lỗi
             * trong khi thao tác THẬT SỰ đã thành công — họ bấm lại, và
             * lần bấm thứ hai bị máy trạng thái từ chối vì đơn đã ở
             * trạng thái đó rồi.
             */
            Log::error('Không gửi được email cập nhật trạng thái đơn.', [
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Báo cho CỬA HÀNG biết khách vừa tự huỷ đơn.
     *
     * VÌ SAO CẦN: khách huỷ là đơn biến mất khỏi danh sách cần giao, mà
     * không ai bấm gì trong trang quản trị. Nếu cửa hàng đã cắt hoa hoặc
     * đã gọi shipper thì phải biết NGAY, không thể đợi lần sau đăng nhập
     * mới phát hiện.
     *
     * Chỉ gửi khi KHÁCH tự huỷ. Cửa hàng tự huỷ thì họ đã biết rồi —
     * gửi thư báo cho chính người vừa bấm nút là thông báo rác.
     *
     * @return bool đã bàn giao cho tầng mail hay chưa
     */
    public function notifyShopOfCancellation(Order $order): bool
    {
        $shopEmail = Setting::get('site_email');

        // Cửa hàng chưa khai email liên hệ thì không có chỗ nào để gửi.
        if (! $shopEmail) {
            return false;
        }

        try {
            $this->transport->deliver(
                new OrderCancelledByCustomerMail($order->loadMissing('items')),
                $shopEmail,
            );

            return true;
        } catch (\Throwable $e) {
            Log::error('Không gửi được thông báo huỷ đơn cho cửa hàng.', [
                'order_number' => $order->order_number,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
