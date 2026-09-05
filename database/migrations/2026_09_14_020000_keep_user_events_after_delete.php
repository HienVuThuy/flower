<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhật ký hành vi phải SỐNG SÓT khi sản phẩm hay danh mục bị xoá.
 * ============================================================
 * VẤN ĐỀ: bảng user_events đang không nhất quán với chính nó.
 *
 *   user_id     -> nullOnDelete    (xoá tài khoản, sự kiện ở lại)
 *   product_id  -> cascadeOnDelete (xoá sản phẩm, sự kiện BIẾN MẤT)
 *   category_id -> cascadeOnDelete (xoá danh mục, sự kiện BIẾN MẤT)
 *
 * Hai cách hiểu trái ngược nhau trong cùng một bảng, và cách hiểu đúng
 * đã được ghi rõ ở đầu AnalyticsService: đây là NHẬT KÝ NHỮNG VIỆC ĐÃ
 * XẢY RA, bản ghi ở lại kể cả khi thứ nó nhắc tới không còn.
 *
 * HẬU QUẢ CỤ THỂ: xoá một danh mục hôm nay làm bốc hơi mọi lượt xem của
 * danh mục đó từ những tháng trước. Báo cáo phễu "xem → giỏ → mua" của
 * tháng Bảy đổi số vào tháng Chín, mà không ai đụng vào tháng Bảy. Số
 * liệu lịch sử tự sửa lại chính nó là số liệu không dùng được để so
 * sánh giữa các kỳ — mà so sánh giữa các kỳ là gần như toàn bộ lý do
 * người ta mở trang báo cáo.
 *
 * Danh mục KHÔNG dùng xoá mềm nên xoá là xoá hẳn — đường này có thật,
 * không phải giả định.
 *
 * Sau khi sửa, sự kiện của sản phẩm đã xoá còn lại với product_id NULL:
 * vẫn đếm được tổng lượt xem toàn cửa hàng, chỉ không quy về sản phẩm
 * nào nữa. Đó đúng là sự thật — sản phẩm ấy không còn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_events', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['category_id']);
        });

        Schema::table('user_events', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_events', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['category_id']);
        });

        Schema::table('user_events', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
        });
    }
};
