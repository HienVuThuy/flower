<?php

namespace App\Mail;

use App\Models\Setting;
use App\Services\Shop\StoreProfile;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Thư chứa mã OTP xác thực email.
 * ============================================================
 * CỐ Ý KHÔNG dùng SerializesModels và KHÔNG dùng ShouldQueue.
 *
 * SerializesModels chỉ cần khi thư được xếp hàng đợi; ở đây thư gửi
 * ngay trong request. Quan trọng hơn: $code là mã GỐC, và xếp hàng đợi
 * nghĩa là mã gốc nằm trong cột `payload` của bảng `jobs` — đúng thứ mà
 * cả thiết kế này cố tránh khi chỉ lưu băm trong cơ sở dữ liệu.
 *
 * Dự án cũng đặt QUEUE_CONNECTION=database mà không chạy `queue:work`,
 * nên đưa vào hàng đợi là thư nằm im mãi mãi — xem OrderConfirmationMail.
 */
class EmailVerificationMail extends Mailable
{
    public function __construct(
        public readonly User $user,
        public readonly string $code,
        public readonly int $expiresInMinutes,
    ) {
    }

    public function envelope(): Envelope
    {
        $shopName = Setting::get('site_name');

        return new Envelope(
            from: $shopName
                ? new Address(config('mail.from.address'), $shopName)
                : null,
            // Đưa mã lên TIÊU ĐỀ: trên điện thoại, khách đọc được mã ngay
            // ở danh sách thư mà không phải mở ra.
            subject: 'Mã xác thực email: '.$this->code,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.verify-email',
            with: [
                'name' => $this->user->name,
                'code' => $this->code,
                'minutes' => $this->expiresInMinutes,
                'hotline' => StoreProfile::hotline(),
            ],
        );
    }
}
