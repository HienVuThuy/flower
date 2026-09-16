<?php

namespace App\Services\Auth;

use App\Mail\EmailVerificationMail;
use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Xác thực email bằng mã OTP 6 chữ số. */
class EmailVerifier
{
    public const TTL_MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public function send(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            throw new EmailVerificationException('Email này đã được xác thực rồi.');
        }

        $khoa = Cache::lock('gui-ma-xac-thuc:'.$user->id, 10);

        if (! $khoa->get()) {
            throw new EmailVerificationException(
                'Đang gửi mã cho bạn, vui lòng đợi một chút.'
            );
        }

        try {
            $this->sendLocked($user);
        } finally {
            $khoa->release();
        }
    }

    private function sendLocked(User $user): void
    {
        if ($seconds = $this->secondsUntilResend($user)) {
            throw new EmailVerificationException(
                "Vui lòng đợi {$seconds} giây nữa rồi hãy gửi lại mã."
            );
        }

        $code = $this->newCode();

        EmailVerificationCode::updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
                'sent_at' => now(),
                'attempts' => 0,
            ],
        );

        Mail::to($user->email)->send(new EmailVerificationMail($user, $code, self::TTL_MINUTES));

        Log::info('Đã gửi mã xác thực email.', ['user_id' => $user->id]);
    }

    public function confirm(User $user, string $input): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $record = EmailVerificationCode::where('user_id', $user->id)->first();

        if (! $record) {
            throw new EmailVerificationException('Chưa có mã nào được gửi. Bấm "Gửi lại mã" để nhận mã mới.');
        }

        if ($record->expires_at->isPast()) {
            $record->delete();

            throw new EmailVerificationException('Mã đã hết hạn. Bấm "Gửi lại mã" để nhận mã mới.');
        }

        $conLuot = EmailVerificationCode::whereKey($record->id)
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->update(['attempts' => DB::raw('attempts + 1')]);

        if ($conLuot === 0) {
            $record->delete();

            throw new EmailVerificationException('Bạn đã nhập sai quá nhiều lần. Bấm "Gửi lại mã" để nhận mã mới.');
        }

        $record->refresh();

        if (! Hash::check($this->normalise($input), $record->code_hash)) {
            $conLai = max(0, self::MAX_ATTEMPTS - $record->attempts);

            throw new EmailVerificationException(
                $conLai > 0
                    ? "Mã không đúng. Bạn còn {$conLai} lần thử."
                    : 'Mã không đúng và bạn đã hết lượt thử. Bấm "Gửi lại mã".'
            );
        }

        $user->markEmailAsVerified();

        $record->delete();

        Log::info('Đã xác thực email.', ['user_id' => $user->id]);
    }

    public function secondsUntilResend(User $user): int
    {
        $record = EmailVerificationCode::where('user_id', $user->id)->first();

        if (! $record?->sent_at) {
            return 0;
        }

        $moUsed = $record->sent_at->addSeconds(self::RESEND_COOLDOWN_SECONDS);

        return $moUsed->isFuture() ? (int) ceil(now()->diffInSeconds($moUsed, absolute: true)) : 0;
    }

    public function expiresAt(User $user): ?Carbon
    {
        return EmailVerificationCode::where('user_id', $user->id)->first()?->expires_at;
    }

    private function newCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function normalise(string $input): string
    {
        return preg_replace('/\D/', '', $input) ?? '';
    }
}
