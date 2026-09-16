<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Báo cho CỬA HÀNG biết khách vừa tự huỷ đơn. */
class OrderCancelledByCustomerMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Huỷ đơn] Khách vừa huỷ ' . $this->order->order_number,

            replyTo: $this->order->recipient_email
                ? [new Address($this->order->recipient_email, $this->order->recipient_name)]
                : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.cancelled-by-customer',
            with: ['order' => $this->order],
        );
    }
}
