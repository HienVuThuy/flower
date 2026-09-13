<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Báo cho CỬA HÀNG biết vừa có đơn mới.
 * ============================================================
 * VÌ SAO CẦN: trước đây thư duy nhất gửi cửa hàng là khi khách TỰ HUỶ.
 * Đơn mới chỉ lộ ra khi có người mở trang quản trị — với hoa tươi, chậm
 * vài tiếng là lỡ giờ giao.
 *
 * Thư nội bộ, cùng khuôn với OrderCancelledByCustomerMail: không cảm ơn,
 * không trang trí, chỉ những gì cần để bắt tay vào làm.
 */
class NewOrderForShopMail extends Mailable
{
    // Bắt buộc khi bật hàng đợi thư — xem chú thích ở OrderCancelledByCustomerMail.
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Đơn mới] ' . $this->order->order_number
                . ' — ' . $this->order->recipient_name,

            // Bấm "Trả lời" là liên hệ thẳng với khách.
            replyTo: $this->order->recipient_email
                ? [new Address($this->order->recipient_email, $this->order->recipient_name)]
                : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.new-for-shop',
            with: ['order' => $this->order],
        );
    }
}
