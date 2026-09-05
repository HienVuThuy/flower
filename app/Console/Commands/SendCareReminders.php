<?php

namespace App\Console\Commands;

use App\Services\Care\CareReminderMailer;
use Illuminate\Console\Command;

/**
 * Gửi thư nhắc chăm cây đã tới hạn.
 *
 * Chạy MỘT LẦN MỖI NGÀY (xem routes/console.php). Không chạy dày hơn:
 * lịch tính theo ngày, chạy mỗi giờ chỉ làm hai mươi ba lượt truy vấn
 * không tìm thấy gì.
 *
 * CHẠY LẠI TRONG CÙNG NGÀY KHÔNG GỬI TRÙNG: mỗi lịch gửi xong là được
 * dời sang kỳ tiếp theo, nên lượt chạy thứ hai không còn thấy nó nữa.
 *
 *     php artisan care:remind
 */
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
            // Nói rõ vì sao có lịch tới hạn mà không gửi — nếu không, con
            // số "đã gửi 3" trong khi có 8 lịch tới hạn trông như lỗi.
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
