<?php

namespace App\Mail;

use App\Models\Refund;
use App\Models\Setting;
use App\Services\Shop\StoreProfile;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Thư báo khách: cửa hàng đã trả lại tiền. */
class RefundMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly Refund $refund,
    ) {
    }

    public function envelope(): Envelope
    {
        $shopEmail = StoreProfile::email();
        $shopName = Setting::get('site_name');

        return new Envelope(
            from: $shopName ? new Address(config('mail.from.address'), $shopName) : null,

            subject: 'Cửa hàng đã hoàn tiền — ' . $this->refund->order->order_number,

            replyTo: $shopEmail ? [new Address($shopEmail)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.refund',
            with: [
                'refund' => $this->refund,
                'order' => $this->refund->order,
                'hotline' => StoreProfile::hotline(),
            ],
        );
    }
}
