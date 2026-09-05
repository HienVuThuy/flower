<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chương trình khuyến mại (campaign).
     *
     * Đây là thực thể độc lập, KHÔNG phải thuộc tính của Product.
     * Nhờ vậy admin tạo một chương trình rồi gắn nhiều sản phẩm vào,
     * thay vì mở từng sản phẩm để sửa giá.
     *
     * theme_key liên kết (lỏng) sang theme giao diện — Promotion
     * quyết định "đang bán chương trình gì", Theme quyết định
     * "website trông thế nào". Cố ý KHÔNG dùng khoá ngoại vì theme
     * là cấu hình trong code (config/theme.php), không phải bảng.
     */
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();
            $table->string('banner')->nullable();

            $table->string('theme_key', 40)->nullable();

            $table->string('type', 30)->default('percent');
            $table->decimal('discount_value', 14, 2)->nullable();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->string('status', 20)->default('draft');

            // Khi một sản phẩm nằm trong nhiều chương trình cùng lúc,
            // chương trình có priority cao hơn được áp dụng.
            $table->unsignedInteger('priority')->default(0);

            $table->timestamps();

            // Truy vấn nóng nhất: "các chương trình đang chạy" —
            // lọc theo status rồi tới khoảng thời gian.
            $table->index(['status', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
