<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ĐỔI HÀNG — khách trả lại món này, nhận món khác. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchanges', function (Blueprint $table) {
            $table->id();

            $table->string('code', 32)->unique();

            $table->foreignId('order_id')->constrained()->restrictOnDelete();

            $table->string('reason', 32);
            $table->string('status', 32)->default('cho_nhan');
            $table->text('note')->nullable();

            $table->decimal('tien_hang_tra', 12, 2)->default(0);
            $table->decimal('tien_hang_moi', 12, 2)->default(0);
            $table->decimal('phi_ship', 12, 2)->default(0);

            $table->decimal('chenh_lech', 12, 2)->default(0);

            $table->decimal('da_thu', 12, 2)->default(0);

            $table->foreignId('refund_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('nhan_hang_at')->nullable();
            $table->timestamp('hoan_tat_at')->nullable();
            $table->timestamp('huy_at')->nullable();
            $table->string('ly_do_huy', 255)->nullable();

            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('exchange_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('exchange_id')->constrained()->cascadeOnDelete();

            $table->string('chieu', 16);

            $table->foreignId('order_item_id')->nullable()->constrained()->restrictOnDelete();

            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();

            $table->string('ten_hang', 255);

            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);

            $table->boolean('restock')->default(false);

            $table->timestamps();

            $table->index(['exchange_id', 'chieu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_items');
        Schema::dropIfExists('exchanges');
    }
};
