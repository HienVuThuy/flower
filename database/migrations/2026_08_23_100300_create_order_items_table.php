<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            /*
             * nullOnDelete chứ không cascade: xoá sản phẩm khỏi cửa hàng
             * KHÔNG được phép xoá dòng trong đơn hàng cũ. Đơn hàng là
             * chứng từ, phải giữ nguyên.
             */
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

            /* ---------- BẢN CHỤP tại thời điểm đặt ----------
             * Đây là phần quan trọng nhất của cả module.
             * Sản phẩm có thể đổi tên, đổi giá, chương trình khuyến mại
             * có thể kết thúc — đơn hàng đã đặt PHẢI giữ nguyên con số
             * và tên gọi lúc khách bấm mua. Không bao giờ hiển thị đơn
             * cũ bằng cách join sang bảng products để lấy giá hiện tại.
             */
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->string('variant_name')->nullable();
            $table->string('promotion_name')->nullable();

            $table->decimal('unit_base_price', 12, 2);   // giá gốc
            $table->decimal('unit_price', 12, 2);        // giá thực trả sau giảm
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 12, 2);        // unit_price * quantity

            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
