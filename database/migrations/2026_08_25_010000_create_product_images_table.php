<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thư viện ảnh của sản phẩm.
     *
     * `products.main_image` vẫn được giữ làm ảnh đại diện (dùng ở
     * card, danh sách, kết quả tìm kiếm) — bảng này bổ sung các ảnh
     * phụ cho gallery ở trang chi tiết và cho hiệu ứng đổi ảnh khi
     * rê chuột trên card.
     *
     * Không gộp main_image vào bảng này để tránh phải sửa mọi nơi
     * đang đọc nó, và vì "ảnh đại diện" là quan hệ 1-1 có ý nghĩa
     * riêng, không phải một phần tử bất kỳ trong danh sách.
     */
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->string('path');
            $table->string('alt')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
