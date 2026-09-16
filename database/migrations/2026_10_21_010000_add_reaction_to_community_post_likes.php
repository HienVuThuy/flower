<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lượt thích thành CẢM XÚC: thích, yêu thích, haha, wow, buồn.
 * ============================================================
 * THÊM MỘT CỘT, KHÔNG THÊM BẢNG: vẫn là "một người, một bài, một lần" — ràng
 * buộc UNIQUE cũ giữ nguyên ý nghĩa. Đổi cảm xúc là SỬA dòng đã có, không phải
 * thêm dòng mới, nên mọi phép đếm và điểm thưởng cũ vẫn đúng.
 *
 * Giữ tên bảng `community_post_likes`: đổi tên bảng chỉ để đọc cho xuôi thì
 * phải sửa mọi truy vấn đang chạy, đổi lấy đúng một chữ đẹp hơn.
 *
 * Dòng cũ mặc định là "thích" — đúng với việc đã xảy ra: lúc đó chỉ có thích.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_post_likes', function (Blueprint $table) {
            $table->string('reaction', 20)->default('thich')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('community_post_likes', function (Blueprint $table) {
            $table->dropColumn('reaction');
        });
    }
};
