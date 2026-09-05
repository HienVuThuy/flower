<?php

namespace App\Services\Auth;

use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Services\Mail\MailTransport;
use Illuminate\Support\Facades\Log;

/**
 * Gửi thư đặt lại mật khẩu.
 * ============================================================
 * Cùng khuôn với App\Services\Order\OrderMailer và cùng hai nguyên tắc:
 *
 * 1. GỬI HỎNG KHÔNG ĐƯỢC LÀM VỠ LUỒNG.
 *    Ném lỗi ra ngoài sẽ cho khách xem trang lỗi, và tệ hơn: trang lỗi
 *    đó tiết lộ email vừa nhập CÓ tồn tại trong hệ thống (email không
 *    tồn tại thì chẳng có gì để gửi nên không bao giờ lỗi).
 *
 * 2. KHÔNG NÓI DỐI LÀ ĐÃ GỬI.
 *    deliversForReal() dùng chung logic với OrderMailer — xem QĐ-04 và
 *    QĐ-09: mailer giả (log/array/null) hoặc địa chỉ người gửi thuộc tên
 *    miền dành riêng cho tài liệu đều tính là "chưa gửi thật được".
 *
 * VÌ SAO KHÔNG GỘP CHUNG VÀO OrderMailer:
 * Hai việc thuộc hai miền khác nhau — một bên là đơn hàng, một bên là
 * xác thực. Gộp lại thì lớp đơn hàng phải biết về mật khẩu. Phần kiểm
 * tra "gửi được thật không" mới là thứ dùng chung, nên nó nằm riêng ở
 * MailTransport để cả hai cùng gọi mà không lớp nào phụ thuộc lớp kia.
 */
class PasswordResetMailer
{
    public function __construct(
        private readonly MailTransport $transport,
    ) {
    }

    public function deliversForReal(): bool
    {
        return $this->transport->deliversForReal();
    }

    /**
     * @return bool đã bàn giao cho tầng mail hay chưa (không phải đã tới hộp thư)
     */
    public function send(User $user, string $resetUrl, int $expiresInMinutes): bool
    {
        try {
            $this->transport->deliver(
                new PasswordResetMail($user, $resetUrl, $expiresInMinutes),
                $user->email,
            );

            return true;
        } catch (\Throwable $e) {
            /*
             * KHÔNG ghi địa chỉ email vào log.
             *
             * Tệp log thường được đọc rộng rãi hơn cơ sở dữ liệu. Ghi id
             * là đủ để lần ra tài khoản khi cần, mà không rải địa chỉ
             * email của khách khắp nơi.
             */
            Log::error('Không gửi được thư đặt lại mật khẩu.', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
