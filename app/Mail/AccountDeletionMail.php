<?php

namespace App\Mail;

use App\Models\Setting;
use App\Models\User;
use App\Services\Shop\StoreProfile;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Thư xác nhận yêu cầu xoá tài khoản. */
class AccountDeletionMail extends Mailable
{
    public function __construct(
        public readonly User $user,
        public readonly string $confirmUrl,
        public readonly int $expiresInMinutes,
    ) {
    }

    public function envelope(): Envelope
    {
        $shopName = Setting::get('site_name');

        return new Envelope(
            from: $shopName
                ? new Address(config('mail.from.address'), $shopName)
                : null,
            subject: 'Xác nhận yêu cầu xoá tài khoản',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.delete-account',
            with: [
                'name' => $this->user->name,
                'url' => $this->confirmUrl,
                'minutes' => $this->expiresInMinutes,
                'hotline' => StoreProfile::hotline(),
            ],
        );
    }
}
