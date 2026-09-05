<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sản phẩm áp dụng cho một chương trình.
     *
     * discount_type / discount_value ở đây là GHI ĐÈ tuỳ chọn: để
     * trống thì lấy theo mức chung của chương trình. Nhờ vậy admin
     * đặt "toàn chương trình giảm 20%" nhưng vẫn chỉnh riêng được
     * một vài sản phẩm mà không cần tách thành chương trình khác.
     *
     * promotional_price là giá đã tính sẵn tại thời điểm lưu — dùng
     * để hiển thị nhanh trong bảng admin và sắp xếp/lọc theo giá sau
     * KM. PricingService vẫn tính lại khi render cho khách, nên nếu
     * giá gốc đổi mà quên đồng bộ thì khách KHÔNG bị tính sai —
     * cột này chỉ là cache hiển thị, không phải nguồn sự thật.
     */
    public function up(): void
    {
        Schema::create('promotion_product', function (Blueprint $table) {
            $table->id();

            $table->foreignId('promotion_id')
                ->constrained('promotions')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->string('discount_type', 30)->nullable();
            $table->decimal('discount_value', 14, 2)->nullable();
            $table->decimal('promotional_price', 14, 2)->nullable();

            $table->timestamps();

            // Một sản phẩm chỉ xuất hiện một lần trong cùng chương trình.
            $table->unique(['promotion_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_product');
    }
};
