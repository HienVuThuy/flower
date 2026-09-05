<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();

            /*
             * Sổ địa chỉ chỉ dành cho tài khoản đã đăng nhập.
             * Khách vãng lai không có chỗ để lưu, và cũng không nên lưu
             * địa chỉ theo phiên: phiên hết hạn là mất, mà giữ lại thì
             * máy dùng chung sẽ lộ địa chỉ người trước.
             */
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('recipient_name');
            $table->string('recipient_phone', 20);
            $table->string('recipient_email')->nullable();

            $table->string('address_line');
            $table->string('ward')->nullable();
            $table->string('district')->nullable();
            $table->string('province');

            $table->string('label', 20)->default('home');
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            // Truy vấn duy nhất dùng tới: lấy sổ của một người, mặc định lên đầu.
            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
