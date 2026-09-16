<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cho phép dọn ví voucher mà KHÔNG phá bằng chứng chống gian lận. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_user', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('used_count');
        });
    }

    public function down(): void
    {
        Schema::table('coupon_user', function (Blueprint $table) {
            $table->dropColumn('hidden_at');
        });
    }
};
