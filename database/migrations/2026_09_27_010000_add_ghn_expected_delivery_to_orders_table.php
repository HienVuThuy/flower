<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Khoảng thời gian GHN dự kiến giao hàng.
 *
 * VÌ SAO LƯU LẠI THAY VÌ HỎI MỖI LẦN MỞ TRANG: đây là một cuộc gọi HTTP
 * ra ngoài. Đặt nó vào lúc dựng trang đơn hàng nghĩa là mỗi lượt xem của
 * khách phải đợi GHN trả lời, và GHN chậm thì trang đơn chậm theo — cho
 * một con số đổi vài ngày một lần.
 *
 * Hai cột chứ không phải một: GHN trả về một KHOẢNG (`leadtime_order`
 * có `from_estimate_date` và `to_estimate_date`), và nói với khách "giao
 * ngày 12" trong khi cam kết của bên vận chuyển là "12 đến 13" là hứa
 * chặt hơn thứ mình nhận được.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('ghn_expected_from')->nullable()->after('shipping_status');
            $table->timestamp('ghn_expected_to')->nullable()->after('ghn_expected_from');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['ghn_expected_from', 'ghn_expected_to']);
        });
    }
};
