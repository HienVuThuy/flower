<?php

namespace App\Mail;

use App\Models\Setting;
use App\Models\User;
use App\Services\Shop\StoreProfile;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Thư xác nhận yêu cầu xoá tài khoản.
 * ============================================================
 * KHÔNG xếp hàng đợi, cùng lý do với EmailVerificationMail: dự án đặt
 * QUEUE_CONNECTION=database mà không chạy `queue:work`, nên vào hàng đợi
 * là thư nằm im mãi mãi. Ở đây thư CHÍNH LÀ chức năng — không có thư thì
 * không ai xoá được tài khoản.
 *
 * THƯ NÀY CŨNG LÀ MỘT CẢNH BÁO. Nếu người nhận không hề yêu cầu xoá, thư
 * là dấu hiệu ai đó đang dùng tài khoản của họ. Vì vậy nội dung phải nói
 * rõ "không phải bạn thì hãy đổi mật khẩu ngay", chứ không chỉ đưa ra
 * một cái liên kết.
 */
class AccountDeletionMail extends Mailable
{
    public function __construct(
        public readonly User $user,
        public readonly string $confirmUrl,
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
            subject: 'Xác nhận yêu cầu xoá tài khoản',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.delete-account',
            with: [
                'name' => $this->user->name,
                'url' => $this->confirmUrl,
                'minutes' => $this->expiresInMinutes,
                'hotline' => StoreProfile::hotline(),
            ],
        );
    }
}
