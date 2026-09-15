<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quyền lợi hạng thành viên khi thanh toán.
 * ============================================================
 * coupons.stack_with_member — mã có được cộng dồn với giảm giá theo hạng
 * không. MẶC ĐỊNH KHÔNG: không cộng dồn vô hạn các loại giảm giá. Cửa hàng
 * bật riêng cho từng mã khi muốn.
 *
 * coupons.min_member_tier_id — "ưu tiên voucher": mã chỉ dành cho hạng này
 * trở lên. Hạng bị xoá thì mã về NULL (mọi khách) — nhưng hạng không xoá
 * được ở trang quản trị, nên chỉ xảy ra khi sửa tay cơ sở dữ liệu.
 *
 * orders.member_tier_code / member_discount — CHỤP vào đơn, cùng lý do với
 * mã giảm giá: sửa ngưỡng hạng sau này không được làm đơn cũ đổi số tiền.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->boolean('stack_with_member')->default(false)->after('per_user_limit');
            $table->foreignId('min_member_tier_id')->nullable()->after('stack_with_member')
                ->constrained('member_tiers')->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('member_tier_code', 20)->nullable()->after('points_discount');
            $table->decimal('member_discount', 12, 2)->default(0)->after('member_tier_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['member_tier_code', 'member_discount']);
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('min_member_tier_id');
            $table->dropColumn('stack_with_member');
        });
    }
};
