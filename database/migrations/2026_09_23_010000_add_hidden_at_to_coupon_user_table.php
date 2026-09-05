<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cho phép dọn ví voucher mà KHÔNG phá bằng chứng chống gian lận.
 * ============================================================
 * VẤN ĐỀ: mã đã lưu mà hết hạn hoặc đã dùng thì nằm lại trong ví vĩnh
 * viễn, không có cách nào bỏ đi. Ví đầy dần bằng những mã không dùng
 * được nữa, và mã còn dùng được thì lẫn vào giữa.
 *
 * VÌ SAO KHÔNG CHO XOÁ THẲNG HÀNG `coupon_user`:
 *
 * Hàng đó là BẰNG CHỨNG khách đã dùng mã này mấy lần, và `per_user_limit`
 * đếm dựa vào nó. Xoá đi là họ dùng lại được từ đầu — một mã "mỗi người
 * một lần" thành mã không giới hạn cho ai biết bấm nút xoá.
 *
 * CÁCH GIẢI: tách hai nhu cầu ra.
 *
 *   - Mã CHƯA dùng lần nào  -> xoá hàng thật. Không có gì để giữ: chưa
 *     dùng thì không có bằng chứng nào cả.
 *   - Mã ĐÃ dùng            -> chỉ đánh dấu `hidden_at`. Hàng còn nguyên,
 *     `per_user_limit` vẫn đếm đúng, nhưng nó biến khỏi danh sách ví.
 *
 * Người dùng chỉ muốn dọn màn hình cho gọn; họ không đòi xoá lịch sử của
 * chính mình. Đáp ứng đúng nhu cầu đó thì không phải nới lỏng gì cả.
 *
 * `hidden_at` chứ không phải cột boolean `is_hidden`: biết mã bị ẩn thì
 * hữu ích, biết ẩn TỪ BAO GIỜ thì hữu ích hơn khi cần dò lại một khiếu
 * nại. Cùng lý do đã dùng cho `journal_milestones.done_at` (QĐ-149).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_user', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('used_count');
        });
    }

    public function down(): void
    {
        Schema::table('coupon_user', function (Blueprint $table) {
            $table->dropColumn('hidden_at');
        });
    }
};
