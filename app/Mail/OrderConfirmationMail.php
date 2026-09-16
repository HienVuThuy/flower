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

/** Email xác nhận đơn hàng gửi cho khách. */
class OrderConfirmationMail extends Mailable
{
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
            subject: 'Xác nhận đơn hàng ' . $this->order->order_number,
            replyTo: $shopEmail ? [new Address($shopEmail)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.confirmation',
            with: [
                'order' => $this->order->loadMissing('items'),
                'hotline' => StoreProfile::hotline(),

            ],
        );
    }
}
