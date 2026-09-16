<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nâng cấp Góc cây: nhiều ảnh / video, bình luận trả lời lồng, lưu bài, báo cáo.
 * ============================================================
 * VẪN "NHẸ": không người theo dõi, không bảng tin theo thuật toán, không nhắn
 * tin. Chỉ làm đủ những việc một bài đăng kiểu mạng xã hội cần để dùng được.
 *
 * 1. `community_post_media` thay cột `photo`: một bài nhiều ảnh / video, có thứ
 *    tự. Ảnh cũ chuyển sang bảng mới rồi mới bỏ cột — không mất ảnh nào.
 * 2. Bình luận có `parent_id` MỘT TẦNG như Facebook: trả lời một câu trả lời thì
 *    gắn vào bình luận gốc và ghi `reply_to_user_id` để hiện "trả lời Tên".
 *    Lồng vô hạn tầng thì trên điện thoại thụt lề tới hết màn hình.
 * 3. `community_post_saves`: bài khách lưu để xem lại.
 * 4. `community_reports`: báo cáo bài / bình luận cho cửa hàng xử lý. Mỗi người
 *    báo một nội dung một lần (UNIQUE) — bấm mười lần không thành mười báo cáo.
 * 5. Bài có `hidden_at`: cửa hàng ẩn bài ĐÃ DUYỆT sau báo cáo, không phải xoá —
 *    còn dấu vết, bỏ ẩn được, và tác giả thấy lý do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_post_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10); // image | video
            $table->string('path');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['community_post_id', 'sort_order']);
        });

        // Ảnh cũ sang bảng mới TRƯỚC khi bỏ cột.
        $bayGio = now();
        DB::table('community_posts')->whereNotNull('photo')->orderBy('id')->get(['id', 'photo'])
            ->each(fn ($p) => DB::table('community_post_media')->insert([
                'community_post_id' => $p->id, 'kind' => 'image', 'path' => $p->photo,
                'sort_order' => 0, 'created_at' => $bayGio, 'updated_at' => $bayGio,
            ]));

        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropColumn('photo');
        });

        Schema::table('community_posts', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('reject_reason');
            $table->string('hidden_reason', 200)->nullable()->after('hidden_at');
            // Bài đã sửa sau khi đăng — hiện "đã chỉnh sửa" như mạng xã hội.
            $table->timestamp('edited_at')->nullable()->after('hidden_reason');
        });

        Schema::table('community_comments', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('user_id')
                ->constrained('community_comments')->cascadeOnDelete();
            $table->foreignId('reply_to_user_id')->nullable()->after('parent_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at')->nullable()->after('hidden_at');
        });

        Schema::create('community_post_saves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['community_post_id', 'user_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('community_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            // post | comment. Không dùng khoá ngoại đa hình: xoá nội dung thì dịch vụ dọn báo cáo theo.
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->string('reason', 30);
            $table->string('note', 300)->nullable();
            $table->string('status', 20)->default('cho_xu_ly');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->unique(['reporter_id', 'target_type', 'target_id']);
            $table->index(['status', 'target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_reports');
        Schema::dropIfExists('community_post_saves');

        Schema::table('community_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reply_to_user_id');
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('edited_at');
        });

        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropColumn(['hidden_at', 'hidden_reason', 'edited_at']);
            $table->string('photo')->nullable()->after('body');
        });

        // Ảnh đầu tiên quay về cột cũ.
        DB::table('community_post_media')->where('kind', 'image')->orderBy('sort_order')->get()
            ->unique('community_post_id')
            ->each(fn ($m) => DB::table('community_posts')->where('id', $m->community_post_id)->update(['photo' => $m->path]));

        Schema::dropIfExists('community_post_media');
    }
};
