<?php

namespace App\Mail;

use App\Models\BulkOrderInquiry;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Báo cho CỬA HÀNG biết vừa có yêu cầu báo giá số lượng lớn.
 * ============================================================
 * Yêu cầu loại này thường là tiệc cưới, khai trương — có NGÀY CỐ ĐỊNH.
 * Để nó nằm trong trang quản trị chờ ai đó tình cờ mở ra là mất khách.
 */
class NewBulkInquiryForShopMail extends Mailable
{
    // Bắt buộc khi bật hàng đợi thư — xem chú thích ở OrderCancelledByCustomerMail.
    use SerializesModels;

    public function __construct(
        public readonly BulkOrderInquiry $inquiry,
    ) {
    }

    public function envelope(): Envelope
    {
        $ngay = $this->inquiry->event_date?->format('d/m/Y');

        return new Envelope(
            subject: '[Báo giá] ' . $this->inquiry->contact_name
                . ($ngay ? ' — sự kiện ' . $ngay : ''),

            replyTo: $this->inquiry->contact_email
                ? [new Address($this->inquiry->contact_email, $this->inquiry->contact_name)]
                : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.bulk-inquiry-for-shop',
            with: ['inquiry' => $this->inquiry],
        );
    }
}
