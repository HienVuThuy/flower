<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mục 4.5 Guide.docx — "Liên hệ khi đặt số lượng lớn".
     * Khách có thể gửi yêu cầu mà không cần tài khoản (guest),
     * nên user_id để nullable.
     */
    public function up(): void
    {
        Schema::create('bulk_order_inquiries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();

            $table->string('contact_name');
            $table->string('contact_phone', 30);
            $table->string('contact_email')->nullable();
            $table->string('occasion')->nullable();
            $table->unsignedInteger('quantity_estimate')->nullable();
            $table->text('message')->nullable();

            $table->string('status', 20)->default('new')->index();

            $table->foreignId('handled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('handled_at')->nullable();
            $table->text('admin_notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_order_inquiries');
    }
};
