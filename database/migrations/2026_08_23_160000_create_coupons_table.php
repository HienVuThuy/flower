<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            /*
             * COUPON KHÁC PROMOTION:
             *   - Promotion: cửa hàng chủ động áp cho một nhóm sản phẩm,
             *     khách không phải làm gì (bảng promotions).
             *   - Coupon: khách phải NHẬP MÃ mới được giảm, và giảm trên
             *     tổng tiền hàng của đơn chứ không trên từng sản phẩm.
             * Hai thứ có thể cùng áp trên một đơn.
             */
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('description')->nullable();

            $table->string('type', 20);
            $table->decimal('value', 12, 2);

            // Điều kiện áp dụng
            $table->decimal('min_order_amount', 12, 2)->nullable();
            // Trần giảm, chỉ có ý nghĩa với kiểu phần trăm.
            $table->decimal('max_discount_amount', 12, 2)->nullable();

            /*
             * Giới hạn lượt dùng. used_count tăng khi ĐẶT HÀNG THÀNH
             * CÔNG, không tăng lúc khách bấm "Áp dụng" — thử mã rồi bỏ
             * giỏ không được tính là đã dùng.
             */
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->string('status', 20)->default('draft');

            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            /*
             * Mã và số tiền giảm được CHỤP vào đơn.
             * Xoá coupon khỏi hệ thống không được làm đơn cũ mất dấu vết
             * đã dùng mã gì và giảm bao nhiêu.
             */
            $table->foreignId('coupon_id')->nullable()->after('payment_status')->constrained()->nullOnDelete();
            $table->string('coupon_code', 32)->nullable()->after('coupon_id');
            $table->decimal('coupon_discount', 12, 2)->default(0)->after('discount_total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['coupon_code', 'coupon_discount']);
        });

        Schema::dropIfExists('coupons');
    }
};
