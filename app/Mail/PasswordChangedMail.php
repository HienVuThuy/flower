<?php

namespace App\Mail;

use App\Models\Setting;
use App\Services\Shop\StoreProfile;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Báo cho chủ tài khoản biết mật khẩu vừa bị đổi. */
class PasswordChangedMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $changedAt,
        public readonly ?string $ipAddress = null,
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
            subject: 'Mật khẩu tài khoản của bạn vừa được thay đổi',
            replyTo: $shopEmail ? [new Address($shopEmail)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.password-changed',
            with: [
                'name' => $this->user->name,
                'changedAt' => $this->changedAt,
                'ipAddress' => $this->ipAddress,
                'hotline' => StoreProfile::hotline(),
            ],
        );
    }
}
