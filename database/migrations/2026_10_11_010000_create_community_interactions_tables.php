<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Góc cây: lượt thích và bình luận.
 * ============================================================
 * Mở rộng có chừng mực quyết định "cố ý nhẹ" của Góc cây: vẫn không có
 * người theo dõi, không bảng tin theo thuật toán, không nhắn tin. Chỉ thêm
 * hai cách phản hồi một bài — và chính hai thứ này là con số tương tác thật
 * để thưởng điểm (trước đây không có gì để đo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            // Một người thích một bài một lần — chặn ở cơ sở dữ liệu, không ở nút bấm.
            $table->unique(['community_post_id', 'user_id']);
        });

        Schema::create('community_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_post_id')->constrained()->cascadeOnDelete();

            // Xoá tài khoản thì bình luận đi theo, cùng lý do với bài đăng.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('body', 500);

            /*
             * Cửa hàng ẨN chứ không xoá: còn dấu vết đã xử lý, và bỏ ẩn được
             * nếu ẩn nhầm. Khách tự gỡ bình luận của mình thì xoá thật.
             */
            $table->timestamp('hidden_at')->nullable();

            $table->timestamps();

            $table->index(['community_post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_comments');
        Schema::dropIfExists('community_post_likes');
    }
};
