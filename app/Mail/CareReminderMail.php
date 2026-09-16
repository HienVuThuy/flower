<?php

namespace App\Mail;

use App\Models\CareReminder;
use App\Models\Setting;
use App\Services\Shop\StoreProfile;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Nhắc khách tưới nước / bón phân cho cây họ đã mua. */
class CareReminderMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly \Illuminate\Support\Collection $reminders,
        public readonly string $customerName,
    ) {
    }

    public function envelope(): Envelope
    {
        $shopEmail = StoreProfile::email();
        $shopName = Setting::get('site_name');

        $count = $this->reminders->count();

        return new Envelope(
            from: $shopName
                ? new Address(config('mail.from.address'), $shopName)
                : null,
            subject: $count === 1
                ? $this->reminders->first()->kind->headline()
                : "Có {$count} việc chăm cây cần làm hôm nay",
            replyTo: $shopEmail ? [new Address($shopEmail)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.care.reminder',
            with: [
                'name' => $this->customerName,
                'reminders' => $this->reminders,
                'hotline' => StoreProfile::hotline(),
            ],
        );
    }
}
