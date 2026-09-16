<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Quyền lợi hạng thành viên khi thanh toán. */
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
