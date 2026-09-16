<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DỮ LIỆU HOÁ ĐƠN — tách khỏi Đơn hàng.
 * ⚠️ BẢNG NÀY LƯU DỮ LIỆU HOÁ ĐƠN, KHÔNG PHÁT HÀNH HOÁ ĐƠN ĐIỆN TỬ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('invoice_number', 40)->unique();

            $table->string('buyer_type', 20);

            $table->string('buyer_name', 200);
            $table->string('buyer_tax_code', 20)->nullable();
            $table->string('buyer_address', 300)->nullable();

            $table->string('buyer_email', 255)->nullable();

            $table->decimal('subtotal', 14, 2);
            $table->decimal('tax_total', 14, 2);
            $table->decimal('grand_total', 14, 2);

            $table->json('rate_breakdown')->nullable();

            $table->string('status', 20)->default('draft');
            $table->timestamp('issued_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
