<?php

namespace App\Mail;

use App\Models\Refund;
use App\Models\Setting;
use App\Services\Shop\StoreProfile;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Thư báo khách: cửa hàng đã trả lại tiền.
 * ============================================================
 * CHỈ GỬI KHI TIỀN ĐÃ ĐI — lần hoàn ở trạng thái "Đã hoàn". Báo "tiền
 * đang về" cho một lần MoMo chưa rõ kết quả là hứa một điều chưa chắc
 * xảy ra; nếu nó thành thất bại thì khách chờ một khoản không bao giờ tới.
 *
 * SerializesModels vì cùng lý do với OrderStatusMail: bật hàng đợi thư mà
 * thiếu nó thì toàn bộ model (kèm chuỗi băm mật khẩu của khách) bị ghi vào
 * bảng `jobs`.
 */
class RefundMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly Refund $refund,
    ) {
    }

    public function envelope(): Envelope
    {
        $shopEmail = StoreProfile::email();
        $shopName = Setting::get('site_name');

        return new Envelope(
            from: $shopName ? new Address(config('mail.from.address'), $shopName) : null,

            // Mã đơn trong tiêu đề: thư này nằm chung mạch với các thư khác
            // của cùng đơn trong hộp thư khách.
            subject: 'Cửa hàng đã hoàn tiền — ' . $this->refund->order->order_number,

            replyTo: $shopEmail ? [new Address($shopEmail)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.refund',
            with: [
                'refund' => $this->refund,
                'order' => $this->refund->order,
                'hotline' => StoreProfile::hotline(),
            ],
        );
    }
}
