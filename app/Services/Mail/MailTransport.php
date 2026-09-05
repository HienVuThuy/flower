<?php

namespace App\Services\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Hệ thống có THẬT SỰ gửi được thư tới hộp thư người nhận hay không.
 * ============================================================
 * Tách riêng khỏi OrderMailer vì nay có hai nơi cần câu trả lời này:
 * thư xác nhận đơn hàng và thư đặt lại mật khẩu. Chép logic sang nơi
 * thứ hai thì sớm muộn hai bản sẽ lệch nhau — và lệch ở đây nghĩa là
 * một trong hai chỗ bắt đầu nói dối người dùng.
 *
 * HAI ĐIỀU KIỆN, thiếu một là không gửi thật được:
 *
 *  1. mailer phải là loại gửi thật (không phải log/array/null);
 *  2. địa chỉ người gửi phải thuộc một tên miền CÓ THẬT.
 *
 * Điều kiện 2 bắt cái bẫy dễ mắc nhất khi đưa lên máy chủ: đổi
 * MAIL_MAILER=smtp cho chạy thật nhưng quên MAIL_FROM_ADDRESS vẫn là
 * "hello@example.com" trong tệp mẫu. Lúc đó Laravel gửi đi bình thường,
 * không báo lỗi gì, nhưng Gmail chặn vì SPF không khớp — thư rơi vào
 * Spam hoặc bị trả về. Xem QĐ-09.
 */
class MailTransport
{
    /** Các mailer chỉ dùng để phát triển, không thực sự gửi đi đâu. */
    private const FAKE_MAILERS = ['log', 'array', 'null'];

    /**
     * Tên miền dùng làm ví dụ trong tài liệu, không ai sở hữu thật.
     *
     * RFC 2606 và RFC 6761 dành riêng .example/.test/.invalid/.localhost
     * cho tài liệu và thử nghiệm, còn example.com/net/org do IANA giữ.
     */
    private const PLACEHOLDER_DOMAINS = [
        'example.com', 'example.net', 'example.org',
    ];

    /**
     * Đuôi tên miền cấp cao nhất dành riêng, khớp cả tên miền con.
     *
     * TÁCH RIÊNG khỏi danh sách trên là có lý do: đem str_ends_with()
     * áp cho "example.com" thì "notexample.com" — một tên miền hoàn toàn
     * hợp lệ ai đó có thể sở hữu — cũng bị coi là tên miền ví dụ. Tên
     * miền đầy đủ phải so khớp CHÍNH XÁC (hoặc là tên miền con của nó);
     * chỉ các đuôi dưới đây mới xét theo phần kết thúc.
     */
    private const PLACEHOLDER_TLDS = ['.example', '.test', '.invalid', '.localhost'];

    /**
     * Gửi một lá thư — nơi DUY NHẤT quyết định gửi ngay hay xếp hàng đợi.
     * ============================================================
     * Bốn chỗ trong dự án gửi thư (xác nhận đơn, đổi trạng thái, báo huỷ
     * cho cửa hàng, các thư về tài khoản). Nếu mỗi chỗ tự chọn send() hay
     * queue() thì sớm muộn sẽ có chỗ bị bỏ sót, và hậu quả là một loại
     * thư âm thầm không bao giờ tới nơi trong khi ba loại kia vẫn chạy —
     * loại lỗi mà không ai phát hiện cho tới lúc khách phàn nàn.
     *
     * KHÔNG bắt lỗi ở đây. Mỗi nơi gọi có cách xử lý riêng khi thư hỏng:
     * đặt hàng thì phải nuốt lỗi để không làm hỏng đơn vừa tạo, còn chỗ
     * khác có thể muốn báo lên. Nuốt sẵn ở đây là tước mất quyền đó.
     *
     * @param  string|array<int, string>  $to
     */
    public function deliver(Mailable $mailable, string|array $to): void
    {
        $pending = Mail::to($to);

        if (config('mail.queue_outgoing')) {
            $pending->queue($mailable);

            return;
        }

        $pending->send($mailable);
    }

    public function deliversForReal(): bool
    {
        if (in_array(config('mail.default'), self::FAKE_MAILERS, true)) {
            return false;
        }

        return ! $this->isPlaceholderAddress((string) config('mail.from.address'));
    }

    /** Địa chỉ có nằm trên tên miền dành riêng cho tài liệu không. */
    public function isPlaceholderAddress(string $address): bool
    {
        $at = strrpos($address, '@');

        // Không có địa chỉ người gửi thì cũng không gửi thật được.
        if ($at === false) {
            return true;
        }

        $domain = strtolower(substr($address, $at + 1));

        foreach (self::PLACEHOLDER_TLDS as $tld) {
            if (str_ends_with($domain, $tld)) {
                return true;
            }
        }

        foreach (self::PLACEHOLDER_DOMAINS as $bad) {
            // Chính nó, hoặc tên miền con của nó (mail.example.com).
            if ($domain === $bad || str_ends_with($domain, '.' . $bad)) {
                return true;
            }
        }

        return false;
    }
}
