<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sổ điểm thưởng, và voucher có chủ.
 * ============================================================
 * Xem App\Services\Points\PointLedger cho ba luật của sổ điểm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();

            // Xoá tài khoản thì sổ điểm đi theo — điểm không chuyển được cho ai.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Có dấu: dương là cộng, âm là trừ. Số dư = tổng.
            $table->integer('amount');

            $table->string('reason', 30);

            /*
             * Định danh của VIỆC được cộng điểm ("bai:12", "chuoi:2026-09-14",
             * "voucher:31"). UNIQUE theo từng khách: một việc, một dòng —
             * chặn ở cơ sở dữ liệu nên hai yêu cầu cùng lúc cũng không lọt.
             */
            $table->string('source_key', 100);

            $table->string('note', 200)->nullable();

            // Dòng đổi voucher trỏ tới mã đã phát. Mã bị xoá thì dòng vẫn còn (điểm đã trừ thật).
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'source_key']);
            $table->index(['user_id', 'id']);
        });

        Schema::table('coupons', function (Blueprint $table) {
            /*
             * CHỦ CỦA MÃ — chỉ người này dùng được. NULL = mã thường.
             *
             * cascadeOnDelete, không nullOnDelete: về NULL là mã riêng của
             * một người thành mã ai biết cũng dùng được. Đơn hàng cũ không
             * mất gì — `orders.coupon_id` tự về NULL và `coupon_code` đã
             * chụp lại mã.
             */
            $table->foreignId('owner_user_id')->nullable()->after('promotion_id')
                ->constrained('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_user_id');
        });

        Schema::dropIfExists('point_transactions');
    }
};
