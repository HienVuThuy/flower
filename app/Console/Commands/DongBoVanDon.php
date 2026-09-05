<?php

namespace App\Console\Commands;

use App\Services\Shipping\GHNService;
use App\Services\Shipping\GhnStatusSync;
use Illuminate\Console\Command;

/**
 * Hỏi GHN tình trạng mọi vận đơn đang chạy.
 * ============================================================
 * ĐÂY LÀ PHẦN "TỰ ĐỘNG" CỦA THANH TOÁN COD.
 *
 * Không có lệnh này thì việc xác nhận khách đã trả tiền phải do admin
 * bấm tay, và trạng thái vận đơn hiển thị cho khách đứng yên ở
 * "chờ lấy hàng" mãi mãi. Xem GhnStatusSync để biết luật cập nhật.
 *
 * ============================================================
 * PHẢI CÓ MỘT TIẾN TRÌNH CHẠY NỀN THÌ LỆNH NÀY MỚI TỰ CHẠY:
 *
 *     php artisan schedule:work           (máy cá nhân)
 *     * * * * * php artisan schedule:run  (máy chủ thật, đặt trong cron)
 *
 * Khai lịch trong routes/console.php KHÔNG làm nó tự chạy — cùng cái bẫy
 * đã gặp với hàng đợi email. Chạy tay được bằng `php artisan ghn:dong-bo`.
 */
class DongBoVanDon extends Command
{
    protected $signature = 'ghn:dong-bo';

    protected $description = 'Hỏi GHN tình trạng vận đơn, tự hoàn tất đơn đã giao và ghi nhận tiền COD';

    public function handle(GhnStatusSync $sync, GHNService $ghn): int
    {
        if (! $ghn->configured()) {
            /*
             * KHÔNG trả về mã lỗi. Máy chưa cấu hình GHN (máy của người
             * khác trong nhóm, môi trường kiểm thử) là chuyện bình
             * thường, không phải sự cố. Trả mã lỗi ở đây là mỗi phút
             * cron lại gửi một cảnh báo về một việc không sai.
             */
            $this->warn('Chưa cấu hình GHN (thiếu token hoặc shop_id) — bỏ qua.');

            return self::SUCCESS;
        }

        $ketQua = $sync->syncAll();

        $this->info(sprintf(
            'Đã hỏi %d vận đơn, %d đơn có thay đổi, %d lỗi.',
            $ketQua['da_hoi'],
            $ketQua['da_doi'],
            $ketQua['loi'],
        ));

        // Có lỗi thì nói ra ở mã thoát: cron ghi lại được, và người vận
        // hành thấy được mà không phải đọc log.
        return $ketQua['loi'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
