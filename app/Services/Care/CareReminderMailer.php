<?php

namespace App\Services\Care;

use App\Mail\CareReminderMail;
use App\Models\CareReminder;
use App\Services\Mail\MailTransport;
use Illuminate\Support\Facades\Log;

/**
 * Gửi thư nhắc chăm cây đã tới hạn.
 * ============================================================
 * GOM THEO KHÁCH, KHÔNG GỬI TỪNG LỊCH MỘT.
 *
 * Khách có bốn cây cùng đến hạn tưới hôm nay thì nhận MỘT thư liệt kê
 * bốn cây. Bốn thư riêng trong một phút là cách nhanh nhất để bị đánh
 * dấu spam — và một khi đã bị đánh dấu thì cả thư xác nhận đơn hàng cũng
 * rơi vào hộp rác theo.
 *
 * CHỈ DỜI LỊCH KHI THƯ ĐÃ ĐI ĐƯỢC.
 * Gửi hỏng mà vẫn dời next_due_at thì lần nhắc đó mất hẳn — khách không
 * được nhắc mà hệ thống tưởng đã nhắc rồi. Hỏng thì để nguyên, lần chạy
 * sau thử lại.
 */
class CareReminderMailer
{
    public function __construct(
        private readonly MailTransport $transport,
    ) {
    }

    /**
     * Gửi mọi lịch đã tới hạn.
     *
     * @return array{users: int, reminders: int, skipped: int, failed: int}
     */
    public function sendDue(): array
    {
        $due = CareReminder::query()
            ->due()
            ->with(['user', 'product'])
            ->get()
            /*
             * Lọc TRONG PHP chứ không bằng JOIN sang users.
             *
             * Điều kiện là "khách còn tồn tại, còn email, và chưa tắt
             * công tắc" — viết thành JOIN thì thành ba điều kiện rải rác
             * trong câu truy vấn, mà bảng này luôn nhỏ (mỗi khách vài
             * lịch). Rõ ràng quan trọng hơn ở đây.
             */
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
                /*
                 * KHÔNG ghi email vào log — tệp log được đọc rộng rãi hơn
                 * cơ sở dữ liệu. Ghi id là đủ để lần ra khi cần.
                 */
                Log::error('Không gửi được thư nhắc chăm cây.', [
                    'user_id' => $user->id,
                    'exception' => $e->getMessage(),
                ]);

                $failed += $reminders->count();

                // KHÔNG dời lịch. Lần chạy sau thử lại.
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
