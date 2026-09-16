<?php

namespace App\Services\Auth;

use App\Mail\PasswordChangedMail;
use App\Models\User;
use App\Services\Mail\MailTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Những việc phải làm KÈM THEO khi mật khẩu thay đổi. */
class AccountSecurity
{
    public function __construct(
        private readonly MailTransport $transport,
    ) {
    }

    public function changePassword(
        User $user,
        string $newPassword,
        ?string $keepSessionId = null,
        ?string $ipAddress = null,
    ): int {
        $notifyEmail = $user->email;

        $user->forceFill([
            'password' => Hash::make($newPassword),

            'remember_token' => Str::random(60),
        ])->save();

        $killed = $this->forgetOtherSessions($user, $keepSessionId);

        try {
            $this->transport->deliver(
                new PasswordChangedMail($user, now()->format('H:i d/m/Y'), $ipAddress),
                $notifyEmail,
            );
        } catch (\Throwable $e) {
            Log::error('Không gửi được cảnh báo đổi mật khẩu.', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return $killed;
    }

    public function forgetOtherSessions(User $user, ?string $keepSessionId = null): int
    {
        if (config('session.driver') !== 'database') {
            return 0;
        }

        $query = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id);

        if ($keepSessionId !== null) {
            $query->where('id', '!=', $keepSessionId);
        }

        return $query->delete();
    }
}
