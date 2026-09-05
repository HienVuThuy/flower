<?php

namespace App\Services\Auth;

use App\Mail\EmailVerificationMail;
use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Xác thực email bằng mã OTP 6 chữ số.
 * ============================================================
 * NƠI DUY NHẤT quyết định một mã có dùng được không. Controller chỉ gọi
 * và bắt ngoại lệ; view chỉ hiển thị. Rải mấy phép so sánh này ra chỗ
 * khác là tạo bản sao thứ hai của một luật bảo mật — thứ chắc chắn sẽ
 * lệch, và lệch ở đây nghĩa là ai đó xác thực được tài khoản không phải
 * của mình.
 *
 * BỐN LỚP CHẶN, mỗi lớp cho một kiểu tấn công khác nhau:
 *
 *   1. HẠN DÙNG (15 phút) — email bị lộ về sau không dùng lại được.
 *   2. SỐ LẦN GÕ SAI (5 lần) — chặn dò mã, kể cả khi kẻ tấn công đổi IP
 *      liên tục nên throttle theo IP không bắt được.
 *   3. THỜI GIAN CHỜ GỬI LẠI (60 giây) — chặn dùng hệ thống làm máy gửi
 *      thư rác vào hộp thư người khác.
 *   4. LƯU BĂM, KHÔNG LƯU MÃ GỐC — ai đọc được cơ sở dữ liệu cũng không
 *      xác thực hộ được.
 *
 * Lớp 5 (giới hạn theo IP) nằm ở routes/web.php bằng middleware throttle.
 */
class EmailVerifier
{
    /** Mã sống bao lâu. */
    public const TTL_MINUTES = 15;

    /** Gõ sai quá số này thì mã bị huỷ, phải xin mã mới. */
    public const MAX_ATTEMPTS = 5;

    /** Phải chờ bao lâu mới được xin mã mới. */
    public const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Sinh mã mới, lưu băm, gửi thư.
     *
     * @throws EmailVerificationException khi chưa hết thời gian chờ
     */
    public function send(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            throw new EmailVerificationException('Email này đã được xác thực rồi.');
        }

        /*
         * MỘT KHOÁ NGẮN QUANH CẢ VIỆC GỬI.
         *
         * Không có nó thì phép đếm thời gian chờ cũng là kiểu "đọc rồi
         * mới ghi": bấm hai lần thật nhanh, hoặc trình duyệt gửi lại
         * biểu mẫu, thì cả hai request đều thấy "đã quá 60 giây" và cùng
         * đi tiếp. Khách nhận hai thư, mà chỉ thư đến SAU còn dùng được
         * — vì mã sinh sau ghi đè mã sinh trước. Nhập mã trong thư đầu
         * (thư họ thấy trước) thì báo sai, không hiểu vì sao.
         *
         * Khoá 10 giây là đủ: nó chỉ cần sống qua khoảng cách giữa hai
         * cú bấm, không phải qua cả thời gian chờ 60 giây.
         */
        $khoa = Cache::lock('gui-ma-xac-thuc:'.$user->id, 10);

        if (! $khoa->get()) {
            throw new EmailVerificationException(
                'Đang gửi mã cho bạn, vui lòng đợi một chút.'
            );
        }

        try {
            $this->sendLocked($user);
        } finally {
            $khoa->release();
        }
    }

    /** Phần việc thật của send(), chạy khi đã cầm khoá. */
    private function sendLocked(User $user): void
    {
        if ($seconds = $this->secondsUntilResend($user)) {
            throw new EmailVerificationException(
                "Vui lòng đợi {$seconds} giây nữa rồi hãy gửi lại mã."
            );
        }

        $code = $this->newCode();

        /*
         * updateOrCreate — GHI ĐÈ mã cũ, không thêm dòng mới.
         *
         * Để hai mã cùng sống nghĩa là mã cũ (có thể đã lộ trong một hộp
         * thư bị xâm nhập) vẫn dùng được, và số lần đoán mà kẻ tấn công
         * có cũng nhân đôi. attempts về 0 vì đây là mã khác.
         */
        EmailVerificationCode::updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
                'sent_at' => now(),
                'attempts' => 0,
            ],
        );

        /*
         * KHÔNG bọc trong try/catch để nuốt lỗi.
         *
         * Khác với thư xác nhận đơn hàng — đơn đã tạo xong rồi, thư hỏng
         * thì thôi. Ở đây thư CHÍNH LÀ chức năng: gửi hỏng mà vẫn báo
         * "đã gửi" thì khách ngồi đợi một mã không bao giờ tới.
         */
        Mail::to($user->email)->send(new EmailVerificationMail($user, $code, self::TTL_MINUTES));

        // KHÔNG ghi mã vào log — log bị đọc, bị sao lưu, bị dán vào chat.
        Log::info('Đã gửi mã xác thực email.', ['user_id' => $user->id]);
    }

    /**
     * Đối chiếu mã khách gõ. Đúng thì đánh dấu đã xác thực.
     *
     * @throws EmailVerificationException với lý do nói được cho khách
     */
    public function confirm(User $user, string $input): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $record = EmailVerificationCode::where('user_id', $user->id)->first();

        if (! $record) {
            throw new EmailVerificationException('Chưa có mã nào được gửi. Bấm "Gửi lại mã" để nhận mã mới.');
        }

        if ($record->expires_at->isPast()) {
            $record->delete();

            throw new EmailVerificationException('Mã đã hết hạn. Bấm "Gửi lại mã" để nhận mã mới.');
        }

        /*
         * ĐẾM LẦN GÕ TRƯỚC KHI SO SÁNH, VÀ ĐẾM BẰNG MỘT CÂU LỆNH DUY NHẤT.
         *
         * Đếm trước: một request bị ngắt giữa chừng (đóng tab, mất mạng)
         * không được thành một lần đoán miễn phí. Kẻ dò mã phải trả giá
         * cho mọi lần thử, kể cả lần không nhận được trả lời.
         *
         * LỖI ĐUA TRƯỚC KHI SỬA: phép kiểm "đã hết lượt chưa" và phép
         * tăng bộ đếm là hai câu lệnh rời nhau. Bắn 50 request cùng lúc
         * thì cả 50 đều đọc thấy attempts = 0 và cả 50 đều được đoán —
         * giới hạn 5 lần trở thành không giới hạn.
         *
         * Điều đó xoá bỏ thứ DUY NHẤT chặn giữa kẻ tấn công và một mã
         * chỉ có một triệu khả năng, còn sống 15 phút. Không phải lỗi
         * nhỏ: nó là toàn bộ hàng rào.
         *
         * Gộp kiểm và ghi vào MỘT câu UPDATE có điều kiện thì cơ sở dữ
         * liệu phân xử — chỉ đúng 5 câu tìm thấy dòng thoả điều kiện,
         * những câu sau đổi 0 dòng và bị chặn.
         */
        $conLuot = EmailVerificationCode::whereKey($record->id)
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->update(['attempts' => DB::raw('attempts + 1')]);

        if ($conLuot === 0) {
            $record->delete();

            throw new EmailVerificationException('Bạn đã nhập sai quá nhiều lần. Bấm "Gửi lại mã" để nhận mã mới.');
        }

        // Đọc lại số đếm thật sau khi cơ sở dữ liệu đã cộng, để câu
        // "còn mấy lần thử" nói đúng con số.
        $record->refresh();

        if (! Hash::check($this->normalise($input), $record->code_hash)) {
            $conLai = max(0, self::MAX_ATTEMPTS - $record->attempts);

            throw new EmailVerificationException(
                $conLai > 0
                    ? "Mã không đúng. Bạn còn {$conLai} lần thử."
                    : 'Mã không đúng và bạn đã hết lượt thử. Bấm "Gửi lại mã".'
            );
        }

        /*
         * markEmailAsVerified() của Laravel, không tự ghi cột.
         *
         * Nó là nơi khung sườn quy ước, nên mọi thứ dựa vào trạng thái
         * này (middleware `verified`, hàm hasVerifiedEmail) khớp nhau.
         */
        $user->markEmailAsVerified();

        // Mã đã dùng thì không được dùng lại — xoá luôn, không đợi dọn dẹp.
        $record->delete();

        Log::info('Đã xác thực email.', ['user_id' => $user->id]);
    }

    /**
     * Còn bao nhiêu giây nữa mới được gửi lại, 0 nghĩa là gửi được ngay.
     *
     * ĐO TỪ `sent_at`, KHÔNG PHẢI `updated_at`.
     *
     * LỖI ĐÃ XẢY RA: increment('attempts') cũng chạm vào `updated_at`,
     * nên mỗi lần khách gõ sai lại đẩy đồng hồ chờ về 60 giây — đúng
     * người đang cần mã mới nhất lại là người bị chặn. Đo được: thư gửi
     * 5 phút trước, chờ = 0 giây; gõ sai một lần, chờ = 59 giây.
     */
    public function secondsUntilResend(User $user): int
    {
        $record = EmailVerificationCode::where('user_id', $user->id)->first();

        if (! $record?->sent_at) {
            return 0;
        }

        $moUsed = $record->sent_at->addSeconds(self::RESEND_COOLDOWN_SECONDS);

        return $moUsed->isFuture() ? (int) ceil(now()->diffInSeconds($moUsed, absolute: true)) : 0;
    }

    /** Mã hiện tại hết hạn lúc nào (để giao diện đếm ngược). */
    public function expiresAt(User $user): ?Carbon
    {
        return EmailVerificationCode::where('user_id', $user->id)->first()?->expires_at;
    }

    /**
     * Sinh 6 chữ số bằng nguồn ngẫu nhiên MẬT MÃ.
     *
     * random_int chứ không phải rand/mt_rand: hai hàm kia đoán được nếu
     * biết vài giá trị trước đó, và ở đây "đoán được" nghĩa là xác thực
     * được tài khoản người khác.
     */
    private function newCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /** Bỏ khoảng trắng và dấu gạch khách hay chép kèm từ email. */
    private function normalise(string $input): string
    {
        return preg_replace('/\D/', '', $input) ?? '';
    }
}
