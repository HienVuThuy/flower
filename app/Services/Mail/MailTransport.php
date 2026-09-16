<?php

namespace App\Services\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/** Hệ thống có THẬT SỰ gửi được thư tới hộp thư người nhận hay không. */
class MailTransport
{
    private const FAKE_MAILERS = ['log', 'array', 'null'];

    private const PLACEHOLDER_DOMAINS = [
        'example.com', 'example.net', 'example.org',
    ];

    private const PLACEHOLDER_TLDS = ['.example', '.test', '.invalid', '.localhost'];

    public function deliver(Mailable $mailable, string|array $to): void
    {
        $pending = Mail::to($to);

        if (config('mail.queue_outgoing')) {
            $pending->queue($mailable);

            return;
        }

        $pending->send($mailable);
    }

    public function deliversForReal(): bool
    {
        if (in_array(config('mail.default'), self::FAKE_MAILERS, true)) {
            return false;
        }

        return ! $this->isPlaceholderAddress((string) config('mail.from.address'));
    }

    public function isPlaceholderAddress(string $address): bool
    {
        $at = strrpos($address, '@');

        if ($at === false) {
            return true;
        }

        $domain = strtolower(substr($address, $at + 1));

        foreach (self::PLACEHOLDER_TLDS as $tld) {
            if (str_ends_with($domain, $tld)) {
                return true;
            }
        }

        foreach (self::PLACEHOLDER_DOMAINS as $bad) {
            if ($domain === $bad || str_ends_with($domain, '.' . $bad)) {
                return true;
            }
        }

        return false;
    }
}
