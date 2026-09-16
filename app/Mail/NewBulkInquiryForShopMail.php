<?php

namespace App\Mail;

use App\Models\BulkOrderInquiry;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Báo cho CỬA HÀNG biết vừa có yêu cầu báo giá số lượng lớn. */
class NewBulkInquiryForShopMail extends Mailable
{
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
