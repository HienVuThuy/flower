<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sản phẩm khách đánh dấu yêu thích.
     *
     * VÌ SAO CẦN BẢNG RIÊNG, KHÔNG DÙNG session:
     * Danh sách yêu thích phải còn nguyên khi khách đăng nhập lại từ điện
     * thoại. Để trong session thì đóng trình duyệt là mất — lúc đó tính
     * năng chỉ còn là một nút bấm cho vui.
     *
     * VÌ SAO BẮT ĐĂNG NHẬP:
     * Không có tài khoản thì không có chỗ nào để gắn danh sách vào. Đây
     * cũng là lý do duy nhất trong dự án bắt khách phải đăng nhập.
     *
     * Bảng nối thuần tuý: chỉ hai khoá ngoại và thời điểm thêm. Không có
     * cột "ghi chú", "độ ưu tiên"… vì chưa có nghiệp vụ nào cần tới.
     */
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->timestamps();

            // Bấm tim hai lần không tạo hai bản ghi.
            $table->unique(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};
