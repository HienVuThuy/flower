<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();

            $table->unsignedInteger('quantity')->default(1);

            $table->timestamps();

            /*
             * KHÔNG lưu giá ở đây. Giỏ hàng phải luôn hiển thị giá HIỆN
             * TẠI: khách bỏ vào giỏ hôm qua, hôm nay chương trình khuyến
             * mại đổi thì giá trong giỏ phải đổi theo. Giá chỉ được chốt
             * lại một lần duy nhất, vào lúc đặt hàng (order_items).
             */

            // Cùng một sản phẩm + biến thể thì cộng dồn số lượng, không tạo dòng mới.
            $table->unique(['cart_id', 'product_id', 'product_variant_id'], 'cart_items_unique_line');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
