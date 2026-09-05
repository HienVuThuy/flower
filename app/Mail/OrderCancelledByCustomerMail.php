<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Báo cho CỬA HÀNG biết khách vừa tự huỷ đơn.
 * ============================================================
 * Thư nội bộ, người nhận là cửa hàng chứ không phải khách. Vì vậy nội
 * dung khác hẳn các thư kia: không cảm ơn, không mời mua thêm — chỉ
 * những thông tin cần để xử lý ngay (đơn nào, ai đặt, lý do, đã cắt hoa
 * chưa).
 *
 * replyTo là email của KHÁCH, không phải của cửa hàng: nhân viên đọc thư
 * xong bấm "Trả lời" là liên hệ được thẳng với người vừa huỷ.
 */
class OrderCancelledByCustomerMail extends Mailable
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
        return new Envelope(
            subject: '[Huỷ đơn] Khách vừa huỷ ' . $this->order->order_number,

            replyTo: $this->order->recipient_email
                ? [new Address($this->order->recipient_email, $this->order->recipient_name)]
                : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.cancelled-by-customer',
            with: ['order' => $this->order],
        );
    }
}
