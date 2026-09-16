<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chủ bài tự quản lý bài của mình.
 * ============================================================
 * BA CỘT MỚI TRÊN BÀI, mỗi cột một việc khách tự làm được mà không cần nhờ cửa
 * hàng — và không cột nào lấn sang quyền duyệt bài:
 *
 *   - `author_hidden_at`: CHỦ BÀI tạm ẩn bài của mình. Tách khỏi `hidden_at`
 *     (cửa hàng ẩn vì vi phạm) vì hai việc khác hẳn nhau: cửa hàng ẩn thì khách
 *     không được tự mở lại, còn bài mình tự ẩn thì mình bật lại lúc nào cũng
 *     được. Gộp một cột là mất đúng ranh giới đó.
 *   - `comments_locked_at`: khoá bình luận cho một bài (bài cũ bị đào lên, hoặc
 *     chủ bài không muốn bàn thêm).
 *   - `pinned_at`: ghim một bài lên đầu TRANG CÁ NHÂN. Không đụng tới bảng tin
 *     chung — ghim mà đẩy được bài lên bảng tin của mọi người thì ai cũng ghim.
 *
 * `hidden_by` trên bình luận: AI đã ẩn. Chủ bài ẩn thì chính họ mở lại được;
 * cửa hàng ẩn (hidden_by null) thì chủ bài không mở lại được — nếu không, ẩn
 * một bình luận vi phạm xong chủ bài bật lại là xong chuyện.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->timestamp('author_hidden_at')->nullable()->after('hidden_reason');
            $table->timestamp('comments_locked_at')->nullable()->after('author_hidden_at');
            $table->timestamp('pinned_at')->nullable()->after('comments_locked_at');
        });

        Schema::table('community_comments', function (Blueprint $table) {
            $table->foreignId('hidden_by')->nullable()->after('hidden_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('community_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hidden_by');
        });

        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropColumn(['author_hidden_at', 'comments_locked_at', 'pinned_at']);
        });
    }
};
