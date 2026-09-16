<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sổ điểm thưởng, và voucher có chủ. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->integer('amount');

            $table->string('reason', 30);

            $table->string('source_key', 100);

            $table->string('note', 200)->nullable();

            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'source_key']);
            $table->index(['user_id', 'id']);
        });

        Schema::table('coupons', function (Blueprint $table) {
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
