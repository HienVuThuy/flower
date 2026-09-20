<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Khách đăng ký nhận báo khi sản phẩm hết hàng có lại. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'product_id', 'product_variant_id'], 'stock_alerts_khach_san_pham');
            $table->index(['product_id', 'notified_at']);
        });

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('community_comment_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });

        Schema::dropIfExists('stock_alerts');
    }
};
