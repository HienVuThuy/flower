<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| TÁC VỤ ĐỊNH KỲ
|--------------------------------------------------------------------------
|
| Cần một tiến trình chạy nền để những lệnh này thực sự được gọi:
|
|     php artisan schedule:work        (máy cá nhân, chạy trong terminal)
|     * * * * * php artisan schedule:run   (máy chủ thật, đặt trong cron)
|
| Không có tiến trình đó thì lệnh dưới đây KHÔNG BAO GIỜ chạy — giống hệt
| chuyện hàng đợi email đã gặp. Ghi rõ ở đây để người triển khai không
| tưởng rằng khai lịch là xong.
|
*/

/*
 * Dọn token đặt lại mật khẩu đã hết hạn.
 *
 * Token hết hạn KHÔNG dùng lại được (broker kiểm tra thời gian), nên đây
 * không phải lỗ hổng. Nhưng bảng cứ phình mãi, và mỗi hàng còn lại là
 * một mã băm gắn với một địa chỉ email — dữ liệu không còn tác dụng gì
 * thì không có lý do giữ.
 *
 * Chạy hằng ngày là đủ: hạn token chỉ 60 phút.
 */
Schedule::command('auth:clear-resets')
    ->daily()
    ->description('Xoá token đặt lại mật khẩu đã hết hạn');


/*
 * Gửi thư nhắc tưới nước / bón phân.
 *
 * 8 GIỜ SÁNG, không phải nửa đêm như tác vụ dọn dẹp bên trên: đây là thư
 * gửi cho người thật đọc. Nhắc tưới cây lúc 0h thì sáng dậy thư đã nằm
 * dưới một chồng thư khác, và việc cần làm thì không ai làm lúc nửa đêm.
 *
 * Mỗi ngày một lần. Lịch tính theo NGÀY nên chạy dày hơn chỉ tạo thêm
 * hai mươi ba lượt truy vấn không tìm thấy gì.
 */
Schedule::command('care:remind')
    ->dailyAt('08:00')
    ->description('Nhắc khách tưới nước / bón phân cho cây đã mua');


/*
 * Hỏi GHN tình trạng vận đơn.
 *
 * 30 PHÚT MỘT LẦN, không dày hơn và không thưa hơn.
 *
 * Dày hơn: mỗi lượt là một lời gọi API cho MỖI vận đơn đang chạy, và GHN
 * không đổi trạng thái theo phút — hỏi mỗi 5 phút chỉ tốn hạn mức để
 * nhận lại đúng câu trả lời cũ.
 *
 * Thưa hơn: đây là thứ quyết định lúc nào khách nhận được thư "đơn đã
 * giao" và lúc nào tiền COD được ghi nhận. Để nửa ngày mới cập nhật thì
 * trang "đơn của tôi" nói sai suốt nửa ngày đó.
 *
 * withoutOverlapping: lượt trước chưa xong mà lượt sau đã chạy thì hai
 * tiến trình cùng đổi trạng thái một đơn. Mạng chậm hoặc nhiều vận đơn
 * là đủ để chuyện đó xảy ra.
 */
Schedule::command('ghn:dong-bo')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->description('Đồng bộ trạng thái vận đơn GHN, tự ghi nhận COD đã thu');


/*
 * Kéo nhãn trạng thái khuyến mại theo ngày đã đặt.
 *
 * MỖI GIỜ, không phải mỗi ngày. Khuyến mại flash sale đặt kết thúc lúc
 * 12h trưa mà tới nửa đêm mới đổi nhãn thì cả buổi chiều trang quản trị
 * nói sai. Giá bán thì vẫn đúng — Promotion::scopeActiveNow() lọc theo
 * ngày — nhưng nhãn sai là admin đọc sai tình hình cửa hàng mình.
 *
 * Rẻ: hai câu truy vấn có chỉ mục, thường không tìm thấy gì.
 */
Schedule::command('khuyen-mai:cap-nhat-trang-thai')
    ->hourly()
    ->description('Bật/kết thúc khuyến mại theo ngày đã đặt');
