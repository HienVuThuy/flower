<?php

namespace App\Services\Auth;

use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Services\Mail\MailTransport;
use Illuminate\Support\Facades\Log;

/** Gửi thư đặt lại mật khẩu. */
class PasswordResetMailer
{
    public function __construct(
        private readonly MailTransport $transport,
    ) {
    }

    public function deliversForReal(): bool
    {
        return $this->transport->deliversForReal();
    }

    public function send(User $user, string $resetUrl, int $expiresInMinutes): bool
    {
        try {
            $this->transport->deliver(
                new PasswordResetMail($user, $resetUrl, $expiresInMinutes),
                $user->email,
            );

            return true;
        } catch (\Throwable $e) {
            Log::error('Không gửi được thư đặt lại mật khẩu.', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
