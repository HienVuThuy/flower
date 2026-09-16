<?php

namespace App\Services\Care;

use App\Mail\CareReminderMail;
use App\Models\CareReminder;
use App\Services\Mail\MailTransport;
use Illuminate\Support\Facades\Log;

/** Gửi thư nhắc chăm cây đã tới hạn. */
class CareReminderMailer
{
    public function __construct(
        private readonly MailTransport $transport,
    ) {
    }

    public function sendDue(): array
    {
        $due = CareReminder::query()
            ->due()
            ->with(['user', 'product'])
            ->get()
            ->filter(fn (CareReminder $r) => $r->user !== null
                && $r->user->email
                && $r->user->notify_care_reminders !== false
                && $r->product !== null);

        $skipped = CareReminder::query()->due()->count() - $due->count();

        $users = 0;
        $sent = 0;
        $failed = 0;

        foreach ($due->groupBy('user_id') as $reminders) {
            $user = $reminders->first()->user;

            try {
                $this->transport->deliver(
                    new CareReminderMail($reminders->values(), $user->name),
                    $user->email,
                );
            } catch (\Throwable $e) {
                Log::error('Không gửi được thư nhắc chăm cây.', [
                    'user_id' => $user->id,
                    'exception' => $e->getMessage(),
                ]);

                $failed += $reminders->count();

                continue;
            }

            foreach ($reminders as $reminder) {
                $reminder->advance();
                $sent++;
            }

            $users++;
        }

        return [
            'users' => $users,
            'reminders' => $sent,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }
}
