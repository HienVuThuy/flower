<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            /*
             * Giỏ hàng thuộc về NGƯỜI DÙNG nếu đã đăng nhập, ngược lại
             * thuộc về PHIÊN. Khách vãng lai vẫn thêm được vào giỏ; khi
             * họ đăng nhập, giỏ của phiên được gộp vào giỏ của tài khoản.
             */
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable();

            $table->timestamps();

            // Mỗi tài khoản / mỗi phiên chỉ có đúng một giỏ.
            $table->unique('user_id');
            $table->unique('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
