<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phản hồi của cửa hàng cho một đánh giá.
 * ============================================================
 * VÌ SAO CẦN: hiện admin chỉ làm được đúng một việc với đánh giá — ẨN
 * nó đi. Với một đánh giá 2 sao thì đó là lựa chọn tệ nhất có thể:
 * khách viết ra vì muốn được nghe, ẩn đi là nói với họ rằng cửa hàng
 * không muốn nghe. Và người đọc sau đó chỉ thấy toàn 5 sao, nên không
 * tin trang đánh giá nữa.
 *
 * Mọi sàn thật (Shopee, Tiki, Lazada) đều có ô "Phản hồi của người bán",
 * vì một lời xin lỗi công khai kèm cách xử lý cứu được nhiều khách hơn
 * là giấu lời phàn nàn.
 *
 * HAI CỘT, KHÔNG PHẢI BẢNG RIÊNG: một đánh giá có nhiều nhất MỘT phản
 * hồi của cửa hàng. Dựng bảng `review_replies` cho quan hệ một-một là
 * thêm một phép JOIN vào mọi truy vấn đọc đánh giá để đổi lấy khả năng
 * lưu nhiều phản hồi — thứ nghiệp vụ không cần.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->text('admin_reply')->nullable()->after('comment');

            /*
             * Ngày trả lời phải lưu RIÊNG, không suy ra từ `updated_at`.
             *
             * `updated_at` đổi cả khi admin bấm ẩn/hiện đánh giá. Dùng nó
             * làm ngày trả lời thì mỗi lần ẩn rồi hiện lại, trang khách
             * hiện một ngày phản hồi mới toanh cho một câu viết từ tháng
             * trước. Đúng lỗi kiểu này đã gặp ở cột `sent_at` của mã OTP.
             */
            $table->timestamp('admin_replied_at')->nullable()->after('admin_reply');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['admin_reply', 'admin_replied_at']);
        });
    }
};
