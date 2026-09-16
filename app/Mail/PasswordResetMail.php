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

/** Thư chứa liên kết đặt lại mật khẩu. */
class PasswordResetMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $resetUrl,
        public readonly int $expiresInMinutes,
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
            subject: 'Đặt lại mật khẩu tài khoản của bạn',
            replyTo: $shopEmail ? [new Address($shopEmail)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.password-reset',
            with: [
                'name' => $this->user->name,
                'url' => $this->resetUrl,
                'minutes' => $this->expiresInMinutes,
                'hotline' => StoreProfile::hotline(),
            ],
        );
    }
}
