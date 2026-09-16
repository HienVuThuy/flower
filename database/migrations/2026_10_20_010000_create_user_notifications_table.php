<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thông báo trong trang cho khách.
 * ============================================================
 * VÌ SAO CẦN: người đăng bài Góc cây không có cách nào biết có người vừa bình
 * luận hay trả lời mình, ngoài việc tự mở lại bài. Bài được duyệt hay bị từ
 * chối cũng vậy — trước đây phải vào mục "Bài của tôi" mới thấy.
 *
 * KHÔNG LƯU CÂU CHỮ, chỉ lưu SỰ VIỆC: ai làm gì, ở bài nào, bình luận nào. Câu
 * chữ dựng lúc hiển thị, nên đổi tên người hay sửa bài thì thông báo cũ vẫn
 * đúng, và không có bản sao nội dung nào để lệch.
 *
 * KHÔNG GỬI EMAIL: dự án chưa có tiến trình hàng đợi chạy nền, gửi thư đồng bộ
 * trong request sẽ làm chậm đúng lúc khách bấm gửi bình luận. Thư báo là việc
 * riêng, làm sau khi có worker.
 *
 * Xoá bài / bình luận / tài khoản thì thông báo đi theo (cascade) — thông báo
 * trỏ vào hư không còn tệ hơn không có thông báo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();

            // Người NHẬN thông báo.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('type', 30);

            // Người GÂY RA việc đó (null khi là cửa hàng: duyệt, từ chối, ẩn bài).
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('community_post_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('community_comment_id')->nullable()->constrained('community_comments')->cascadeOnDelete();

            // Lý do từ chối / ẩn — thứ người nhận cần để sửa bài.
            $table->string('note', 200)->nullable();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Truy vấn chính: thông báo của tôi, chưa đọc lên trước, mới nhất trước.
            $table->index(['user_id', 'read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
