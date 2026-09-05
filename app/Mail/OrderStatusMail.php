<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Setting;
use App\Services\Shop\StoreProfile;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email báo đơn hàng đổi trạng thái.
 * ============================================================
 * MỘT lớp cho cả bốn mốc (đã xác nhận / đang giao / đã giao / đã huỷ),
 * KHÔNG tách thành bốn Mailable.
 *
 * Bốn lớp gần giống hệt nhau chỉ khác vài câu chữ thì sửa bố cục email
 * phải sửa bốn chỗ, và chắc chắn sẽ có chỗ bị quên. Phần khác nhau —
 * tiêu đề và lời giải thích — nằm ở OrderStatus, tức cùng nơi định nghĩa
 * vòng đời đơn hàng.
 *
 * CỐ Ý KHÔNG dùng ShouldQueue, cùng lý do với OrderConfirmationMail:
 * dự án đặt QUEUE_CONNECTION=database mà không có tiến trình queue:work
 * nào chạy, nên vào hàng đợi là thư nằm im mãi mãi.
 */
class OrderStatusMail extends Mailable
{
    /*
     * SerializesModels — BẮT BUỘC khi bật hàng đợi thư (mail.queue_outgoing).
     *
     * ĐO ĐƯỢC, không phải phòng xa: xếp thư này vào hàng đợi mà thiếu
     * trait, PHP serialize toàn bộ đối tượng model vào cột `payload` của
     * bảng `jobs` — kèm cả CHUỖI BĂM MẬT KHẨU và `remember_token`. Kiểm
     * tra một payload thật cho thấy đúng như vậy: 3988 byte, có chuỗi
     * "$2y$" trong đó.
     *
     * Bảng `jobs` không phải nơi để những thứ đó nằm: nó bị sao lưu, bị
     * đổ ra khi gỡ lỗi, và không có lớp bảo vệ nào riêng. Trait này chỉ
     * lưu tên lớp + khoá chính, rồi nạp lại model lúc gửi.
     */
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {
    }

    public function envelope(): Envelope
    {
        $shopEmail = StoreProfile::email();
        $shopName = Setting::get('site_name');

        return new Envelope(
            from: $shopName
                ? new Address(config('mail.from.address'), $shopName)
                : null,

            /*
             * Mã đơn nằm trong tiêu đề để khách lọc và tìm lại được trong
             * hộp thư — và để bốn email của cùng một đơn nằm chung một
             * mạch hội thoại ở Gmail.
             */
            subject: $this->order->status->customerHeadline()
                . ' — ' . $this->order->order_number,

            replyTo: $shopEmail ? [new Address($shopEmail)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.status',
            with: [
                'order' => $this->order,
                'hotline' => StoreProfile::hotline(),
            ],
        );
    }
}
