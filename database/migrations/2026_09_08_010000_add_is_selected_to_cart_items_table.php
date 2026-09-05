<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chọn từng món trong giỏ để thanh toán.
     * ============================================================
     * VẤN ĐỀ CỦA GIỎ HÀNG CŨ: thanh toán là lấy SẠCH giỏ. Khách để dành
     * ba món chờ lương và muốn mua trước một bó hoa sinh nhật thì phải
     * xoá ba món kia đi, mua xong lại đi tìm và thêm lại. Với cửa hàng
     * hoa thì chuyện này còn thường hơn: người ta hay để sẵn cây cảnh
     * trong giỏ để tính toán, rồi phát sinh nhu cầu mua hoa gấp.
     *
     * MẶC ĐỊNH `true` — thêm vào giỏ là đã chọn.
     *
     * Chọn vậy vì nó giữ nguyên hành vi cũ cho người không quan tâm tới
     * tính năng này: thêm hàng, bấm thanh toán, xong. Mặc định `false`
     * sẽ khiến mọi khách hiện tại bấm thanh toán và gặp một giỏ "chưa
     * chọn món nào" — một bước thừa cho tất cả mọi người để phục vụ một
     * thiểu số.
     *
     * VÌ SAO LÀ CỘT TRÊN `cart_items`, KHÔNG PHẢI SESSION:
     * Giỏ hàng của người đã đăng nhập sống trong cơ sở dữ liệu và theo họ
     * sang máy khác. Lựa chọn để trong session thì mở máy khác lên là mất
     * — và mất một cách khó hiểu, vì giỏ hàng thì vẫn còn nguyên.
     */
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->boolean('is_selected')->default(true)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('is_selected');
        });
    }
};
