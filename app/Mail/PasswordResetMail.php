<?php

namespace App\Mail;

use App\Models\Setting;
use App\Services\Shop\StoreProfile;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Thư chứa liên kết đặt lại mật khẩu.
 * ============================================================
 * CỐ Ý KHÔNG dùng ShouldQueue, cùng lý do với OrderConfirmationMail:
 * dự án đặt QUEUE_CONNECTION=database mà không có tiến trình
 * `queue:work` nào chạy, nên đưa vào hàng đợi là thư nằm im mãi mãi.
 *
 * KHÔNG dùng notification mặc định của Laravel: thư mặc định là tiếng
 * Anh, mang thương hiệu Laravel, và không đi qua được lớp kiểm tra
 * "có gửi thật được không" mà dự án đang dùng.
 *
 * KHÔNG kèm mật khẩu mới hay mật khẩu cũ trong thư. Thư điện tử đi qua
 * nhiều máy chủ trung gian và nằm lại trong hộp thư rất lâu; thứ duy
 * nhất được phép nằm trong đó là một liên kết dùng một lần, có hạn.
 */
class PasswordResetMail extends Mailable
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
        public readonly User $user,
        public readonly string $resetUrl,
        public readonly int $expiresInMinutes,
    ) {
    }

    public function envelope(): Envelope
    {
        $shopEmail = StoreProfile::email();
        $shopName = Setting::get('site_name');

        return new Envelope(
            from: $shopName
                ? new Address(config('mail.from.address'), $shopName)
                : null,
            subject: 'Đặt lại mật khẩu tài khoản của bạn',
            replyTo: $shopEmail ? [new Address($shopEmail)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.password-reset',
            with: [
                'name' => $this->user->name,
                'url' => $this->resetUrl,
                'minutes' => $this->expiresInMinutes,
                'hotline' => StoreProfile::hotline(),
            ],
        );
    }
}
