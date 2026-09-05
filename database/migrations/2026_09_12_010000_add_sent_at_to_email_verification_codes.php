<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tách "lúc gửi thư" ra khỏi updated_at.
 * ============================================================
 * LỖI ĐO ĐƯỢC TRƯỚC KHI SỬA:
 *   1. Gửi mã lúc 10:00.
 *   2. 10:05 khách gõ sai một lần  -> attempts tăng lên 1.
 *   3. Bấm "Gửi lại mã" -> "Vui lòng đợi 59 giây nữa."
 *
 * Nguyên nhân: khoảng chờ gửi lại đo bằng `updated_at`, mà
 * increment('attempts') cũng chạm vào `updated_at`. Nghĩa là MỖI LẦN GÕ
 * SAI lại đẩy đồng hồ chờ về 60 giây — đúng người đang cần mã mới nhất
 * lại là người bị chặn.
 *
 * `updated_at` trả lời "hàng này sửa lần cuối lúc nào" — một câu hỏi của
 * cơ sở dữ liệu. "Thư gửi lúc nào" là câu hỏi NGHIỆP VỤ, phải có cột
 * riêng. Mượn cột này cho câu hỏi kia là lý do sinh ra lỗi trên.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_verification_codes', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('expires_at');
        });

        /*
         * Hàng đang có: lấy created_at làm mốc gửi.
         *
         * Không chính xác tuyệt đối (hàng bị ghi đè khi gửi lại thì
         * created_at giữ nguyên lần tạo đầu), nhưng lệch về phía AN TOÀN
         * CHO KHÁCH: mốc cũ hơn nghĩa là cho gửi lại sớm hơn, không phải
         * chặn oan. Mã chỉ sống 15 phút nên sai số này tự hết.
         */
        DB::table('email_verification_codes')
            ->whereNull('sent_at')
            ->update(['sent_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('email_verification_codes', function (Blueprint $table) {
            $table->dropColumn('sent_at');
        });
    }
};
