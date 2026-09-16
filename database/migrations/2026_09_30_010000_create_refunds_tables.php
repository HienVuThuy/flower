<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Hoàn tiền và hàng trả về. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();

            $table->string('code', 20)->unique();

            $table->decimal('amount', 14, 2);
            $table->string('reason', 20);
            $table->text('note')->nullable();
            $table->string('method', 20);
            $table->string('status', 20)->default('pending');

            $table->string('reference', 100)->nullable();

            $table->string('gateway_request_id', 100)->nullable()->unique();
            $table->json('gateway_response')->nullable();

            $table->timestamp('completed_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_name')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'status']);
        });

        Schema::create('refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->boolean('restock')->default(false);
            $table->timestamps();

            $table->unique(['refund_id', 'order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_items');
        Schema::dropIfExists('refunds');
    }
};
