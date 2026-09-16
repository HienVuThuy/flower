<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('status');
            $table->unsignedSmallInteger('per_user_limit')->nullable()->after('usage_limit');
        });

        Schema::create('coupon_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();

            $table->timestamp('claimed_at');
            $table->unsignedSmallInteger('used_count')->default(0);

            $table->timestamps();

            $table->unique(['user_id', 'coupon_id']);

            $table->index(['user_id', 'claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_user');

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['is_public', 'per_user_limit']);
        });
    }
};
