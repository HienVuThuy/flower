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
 * Báo cho chủ tài khoản biết mật khẩu vừa bị đổi.
 * ============================================================
 * ĐÂY LÀ THƯ CẢNH BÁO, KHÔNG PHẢI THƯ XÁC NHẬN.
 *
 * Người đổi mật khẩu thì đã biết rồi, không cần báo. Thư này tồn tại cho
 * trường hợp NGƯỢC LẠI: kẻ chiếm được tài khoản đổi mật khẩu để khoá
 * chính chủ ra ngoài. Không có thư này thì nạn nhân chỉ phát hiện khi
 * lần sau đăng nhập không được — lúc đó đã muộn.
 *
 * VÌ VẬY nội dung phải nói rõ PHẢI LÀM GÌ nếu không phải họ đổi, và phải
 * gửi tới địa chỉ email GHI NHẬN TRƯỚC KHI đổi (xem AccountSecurity):
 * kẻ tấn công thường đổi email trước rồi mới đổi mật khẩu.
 */
class PasswordChangedMail extends Mailable
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
        public readonly string $changedAt,
        public readonly ?string $ipAddress = null,
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
            subject: 'Mật khẩu tài khoản của bạn vừa được thay đổi',
            replyTo: $shopEmail ? [new Address($shopEmail)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.password-changed',
            with: [
                'name' => $this->user->name,
                'changedAt' => $this->changedAt,
                'ipAddress' => $this->ipAddress,
                'hotline' => StoreProfile::hotline(),
            ],
        );
    }
}
