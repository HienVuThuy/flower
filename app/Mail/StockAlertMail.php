<?php

namespace App\Mail;

use App\Models\Product;
use App\Services\Shop\StoreProfile;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Báo khách biết món họ chờ đã có hàng lại. */
class StockAlertMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly Product $product,
        public readonly string $customerName,
    ) {
    }

    public function envelope(): Envelope
    {
        $shopEmail = StoreProfile::email();

        return new Envelope(
            from: new Address(config('mail.from.address'), StoreProfile::name()),
            subject: $this->product->name . ' đã có hàng lại',
            replyTo: $shopEmail ? [new Address($shopEmail)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.catalog.stock-alert',
            with: [
                'name' => $this->customerName,
                'product' => $this->product,
                'url' => route('shop.products.show', $this->product),
                'hotline' => StoreProfile::hotline(),
            ],
        );
    }
}
