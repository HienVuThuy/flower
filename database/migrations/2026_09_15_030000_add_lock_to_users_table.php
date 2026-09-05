<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Khoá tài khoản — chặn đăng nhập mà KHÔNG xoá dữ liệu.
 * ============================================================
 * NGHIỆP VỤ CỤ THỂ: hiện tại cửa hàng chỉ có đúng một cách xử lý một
 * tài khoản gây rối (gửi đánh giá rác, đặt hàng ảo hàng loạt) — xoá nó
 * đi. Nhưng xoá tài khoản kéo theo:
 *
 *   - Đơn hàng của họ mất người đứng tên (user_id thành NULL), doanh
 *     thu tháng trước không quy về ai được nữa.
 *   - Đánh giá của họ thành "(tài khoản đã xoá)".
 *   - Và nếu xoá nhầm thì không có đường lùi.
 *
 * Cần một bậc trung gian: NGƯỜI KHÔNG VÀO ĐƯỢC NỮA, DỮ LIỆU VẪN NGUYÊN.
 * Mở lại chỉ là gỡ một dấu thời gian.
 *
 * DÙNG MỐC THỜI GIAN, KHÔNG DÙNG CỜ ĐÚNG/SAI. `is_locked = true` chỉ nói
 * "đang khoá"; `locked_at` nói thêm "từ bao giờ" — thứ luôn được hỏi tới
 * khi có tranh cãi, và không tốn thêm gì để lưu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('locked_at')->nullable()->after('email_verified_at');

            /*
             * LÝ DO KHOÁ — hiện cho chính người bị khoá đọc ở màn hình
             * đăng nhập.
             *
             * Chặn ai đó mà không nói vì sao thì họ chỉ nghĩ là trang
             * web hỏng, và việc tiếp theo họ làm là gọi điện, hoặc lập
             * một tài khoản mới. Cả hai đều tốn công cửa hàng hơn là
             * viết sẵn một dòng giải thích.
             */
            $table->string('lock_reason', 255)->nullable()->after('locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['locked_at', 'lock_reason']);
        });
    }
};
