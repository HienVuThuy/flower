<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Setting;
use App\Services\Shop\StoreProfile;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email xác nhận đơn hàng gửi cho khách.
 * ============================================================
 * CỐ Ý KHÔNG dùng ShouldQueue.
 *
 * Dự án đang đặt QUEUE_CONNECTION=database mà không có tiến trình
 * `queue:work` nào chạy. Nếu đưa vào hàng đợi, job sẽ nằm im trong bảng
 * jobs và email KHÔNG BAO GIỜ được xử lý — tệ hơn là không gửi, vì nhìn
 * code lại tưởng đã gửi.
 *
 * Khi nào có worker thật thì thêm implements ShouldQueue là xong.
 */
class OrderConfirmationMail extends Mailable
{
    /*
     * SerializesModels — BẮT BUỘC khi bật hàng đợi thư (mail.queue_outgoing).
     *
     * ĐO ĐƯỢC, không phải phòng xa: xếp thư này vào hàng đợi mà thiếu
     * trait, PHP serialize toàn bộ đối tượng model vào cột `payload` của
     * bảng `jobs` — kèm cả CHUỖI BĂM MẬT KHẨU và `remember_token`. Kiểm
     * tra một payload thật cho thấy đúng như vậy: 3988 byte, có chuỗi
     * "$2y$" trong đó.
     *
     * Bảng `jobs` không phải nơi để những thứ đó nằm: nó bị sao lưu, bị
     * đổ ra khi gỡ lỗi, và không có lớp bảo vệ nào riêng. Trait này chỉ
     * lưu tên lớp + khoá chính, rồi nạp lại model lúc gửi.
     */
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {
    }

    public function envelope(): Envelope
    {
        /*
         * Địa chỉ gửi lấy từ cấu hình hệ thống (phải thuộc tên miền mình
         * kiểm soát), còn địa chỉ trả lời là email cửa hàng do admin
         * nhập — khách bấm "Trả lời" là tới đúng người bán.
         */
        $shopEmail = StoreProfile::email();

        /*
         * Tên người gửi: ưu tiên tên cửa hàng admin đã nhập trong Cài đặt.
         * Chưa nhập thì để Laravel dùng MAIL_FROM_NAME trong .env.
         * KHÔNG viết cứng tên ở đây — đổi tên cửa hàng thì không ai nhớ
         * ra phải sửa một tệp Mailable.
         */
        $shopName = Setting::get('site_name');

        return new Envelope(
            from: $shopName
                ? new Address(config('mail.from.address'), $shopName)
                : null,
            subject: 'Xác nhận đơn hàng ' . $this->order->order_number,
            replyTo: $shopEmail ? [new Address($shopEmail)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.confirmation',
            with: [
                'order' => $this->order->loadMissing('items'),
                'hotline' => StoreProfile::hotline(),

            ],
        );
    }
}
