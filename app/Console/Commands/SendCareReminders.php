<?php

namespace App\Console\Commands;

use App\Services\Care\CareReminderMailer;
use Illuminate\Console\Command;

/** Gửi thư nhắc chăm cây đã tới hạn. */
class SendCareReminders extends Command
{
    protected $signature = 'care:remind';

    protected $description = 'Gửi thư nhắc tưới nước / bón phân cho cây khách đã mua';

    public function handle(CareReminderMailer $mailer): int
    {
        $result = $mailer->sendDue();

        if ($result['reminders'] === 0 && $result['skipped'] === 0) {
            $this->info('Không có lịch chăm nào tới hạn.');

            return self::SUCCESS;
        }

        $this->line(sprintf(
            'Đã gửi %d lời nhắc cho %d khách.',
            $result['reminders'],
            $result['users'],
        ));

        if ($result['skipped'] > 0) {
            $this->line(sprintf(
                'Bỏ qua %d lịch: khách đã tắt nhận thư, không có email, hoặc sản phẩm đã bị xoá.',
                $result['skipped'],
            ));
        }

        if ($result['failed'] > 0) {
            $this->warn(sprintf(
                '%d lịch gửi hỏng — KHÔNG dời hạn, lần chạy sau sẽ thử lại. Xem log.',
                $result['failed'],
            ));
        }

        return self::SUCCESS;
    }
}
