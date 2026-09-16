<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cảm xúc cho BÌNH LUẬN (bài đã có ở community_post_likes).
 * ============================================================
 * Bảng riêng chứ không gộp chung với cảm xúc của bài: hai thứ có khoá ngoại
 * khác nhau, và gộp lại thì phải thêm một cột "loại đối tượng" — mọi truy vấn
 * đếm đều phải nhớ lọc thêm cột đó, quên một chỗ là đếm nhầm.
 *
 * MỘT NGƯỜI MỘT CẢM XÚC cho một bình luận (UNIQUE). Đổi cảm xúc là sửa dòng.
 *
 * KHÔNG THƯỞNG ĐIỂM cho cảm xúc dưới bình luận: bình luận vốn không được thưởng
 * (xem CommunityReward) — thưởng ở đây là mời bình luận rác để xin cảm xúc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_comment_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reaction', 20)->default('thich');
            $table->timestamp('created_at')->nullable();

            $table->unique(['community_comment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_comment_reactions');
    }
};
