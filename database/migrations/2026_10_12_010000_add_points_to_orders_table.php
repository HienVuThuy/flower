<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Điểm thưởng dùng để trừ tiền đơn hàng — CHỤP vào đơn.
 *
 * Cùng lý do với `coupon_code` / `coupon_discount`: đổi tỉ giá điểm sau
 * này không được làm đơn cũ tự đổi số tiền đã giảm, và huỷ đơn phải biết
 * đúng bao nhiêu điểm cần trả lại.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('points_used')->default(0)->after('coupon_discount');
            $table->decimal('points_discount', 12, 2)->default(0)->after('points_used');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['points_used', 'points_discount']);
        });
    }
};
