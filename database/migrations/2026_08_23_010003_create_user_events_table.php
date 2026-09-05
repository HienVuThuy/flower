<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mục 9 & 25 Guide.docx — nền tảng "Kinh tế số" / theo dõi
     * hành vi người dùng cho Recommendation/Analytics sau này.
     *
     * Một bảng generic (event_type) thay vì một bảng riêng cho
     * mỗi loại hành vi, để khi cần thêm 'search', 'add_to_cart'...
     * không phải tạo migration mới — chỉ thêm giá trị event_type.
     *
     * Hiện tại mới ghi nhận 'product_view' và 'category_view'.
     */
    public function up(): void
    {
        Schema::create('user_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('session_id', 100)->nullable();
            $table->string('event_type', 40)->index();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->json('meta')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_events');
    }
};
