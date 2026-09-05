<?php

namespace App\Mail;

use App\Models\CareReminder;
use App\Models\Setting;
use App\Services\Shop\StoreProfile;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Nhắc khách tưới nước / bón phân cho cây họ đã mua.
 * ============================================================
 * THƯ NÀY PHẢI CÓ ÍCH, KHÔNG ĐƯỢC LÀ QUẢNG CÁO TRÁ HÌNH.
 *
 * Nó tới hộp thư khách theo lịch, không do họ yêu cầu từng lần. Cái giá
 * của việc đó là mỗi lá thư phải mang đúng thứ họ cần ngay lúc đó: tên
 * cây, việc cần làm, và lời khuyên cụ thể của chính cây ấy. Nhét thêm
 * "sản phẩm gợi ý" vào đây là biến một dịch vụ sau bán thành thư rác, và
 * lần sau họ tắt luôn.
 *
 * MỘT LÁ THƯ CHO NHIỀU VIỆC CÙNG NGÀY, không phải mỗi việc một thư:
 * khách có bốn cây đến hạn tưới cùng hôm thì nhận MỘT thư liệt kê bốn
 * cây. Bốn thư riêng là cách nhanh nhất để bị đánh dấu spam.
 */
class CareReminderMail extends Mailable
{
    use SerializesModels;

    /**
     * @param  \Illuminate\Support\Collection<int, CareReminder>  $reminders
     */
    public function __construct(
        public readonly \Illuminate\Support\Collection $reminders,
        public readonly string $customerName,
    ) {
    }

    public function envelope(): Envelope
    {
        $shopEmail = StoreProfile::email();
        $shopName = Setting::get('site_name');

        $count = $this->reminders->count();

        return new Envelope(
            from: $shopName
                ? new Address(config('mail.from.address'), $shopName)
                : null,
            /*
             * Tiêu đề nói RÕ VIỆC, không nói chung chung.
             *
             * "Nhắc nhở từ Flower & Plant" thì khách phải mở ra mới biết
             * là gì. "Đến lúc tưới 2 cây trong nhà bạn" thì họ quyết được
             * ngay trên danh sách hộp thư — và đó là một thư có ích chứ
             * không phải một thư phải xử lý.
             */
            subject: $count === 1
                ? $this->reminders->first()->kind->headline()
                : "Có {$count} việc chăm cây cần làm hôm nay",
            replyTo: $shopEmail ? [new Address($shopEmail)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.care.reminder',
            with: [
                'name' => $this->customerName,
                'reminders' => $this->reminders,
                'hotline' => StoreProfile::hotline(),
            ],
        );
    }
}
