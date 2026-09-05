<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mã OTP xác thực email.
 * ============================================================
 * VÌ SAO CÓ BẢNG NÀY: cách mặc định của Laravel là gửi một LIÊN KẾT có
 * chữ ký. Ở đây dùng MÃ 6 CHỮ SỐ vì khách hay mở email trên điện thoại
 * rồi quay lại gõ trên máy tính — bấm liên kết ở máy này thì phiên đăng
 * nhập lại nằm ở máy kia. Gõ 6 chữ số thì không phụ thuộc thiết bị.
 *
 * MỖI TÀI KHOẢN CHỈ MỘT MÃ SỐNG (user_id là khoá duy nhất). Gửi lại là
 * GHI ĐÈ, không phải thêm dòng: để nhiều mã cùng hiệu lực nghĩa là mã cũ
 * bị lộ vẫn dùng được, và số lần đoán mà kẻ tấn công có cũng nhân lên.
 *
 * LƯU BĂM, KHÔNG LƯU MÃ GỐC. Mã 6 chữ số là một bí mật ngắn hạn, nhưng
 * vẫn là bí mật: ai đọc được cơ sở dữ liệu (bản sao lưu, log truy vấn)
 * không được phép xác thực hộ người khác.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_verification_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            // Băm bcrypt, không phải mã gốc — xem chú thích đầu tệp.
            $table->string('code_hash');

            $table->timestamp('expires_at');

            /*
             * Số lần gõ sai. Mã chỉ có 6 chữ số nên không gian đoán là
             * 1 triệu — nghe thì lớn, nhưng một script gõ vài nghìn lần
             * mỗi phút sẽ dò ra trong vài giờ. Đếm ở đây để khoá mã lại
             * sau vài lần sai, chứ không chỉ dựa vào throttle theo IP
             * (đổi IP là qua được).
             */
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamps();
        });

        /*
         * TÀI KHOẢN CŨ ĐƯỢC COI LÀ ĐÃ XÁC THỰC.
         *
         * Họ đăng ký khi hệ thống chưa có bước này, nên chưa từng có cơ
         * hội xác thực. Bật middleware `verified` mà không làm việc này
         * là KHOÁ TOÀN BỘ khách hiện có ra khỏi tài khoản của họ —
         * "không được phá vỡ chức năng đang hoạt động" đúng nghĩa đen.
         */
        DB::table('users')->whereNull('email_verified_at')->update([
            'email_verified_at' => now(),
        ]);
    }

    public function down(): void
    {
        /*
         * KHÔNG đặt lại email_verified_at về null khi rollback.
         *
         * Không phân biệt được ai đã xác thực thật với ai được đánh dấu
         * bởi migration này, nên xoá hết là làm mất dữ liệu thật của
         * khách. Thà để thừa một dấu xác thực còn hơn mất.
         */
        Schema::dropIfExists('email_verification_codes');
    }
};
