<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mô tả chi tiết theo KHỐI: chữ – ảnh – chữ – ảnh…
 * ============================================================
 * VÌ SAO KHÔNG NHÉT ẢNH VÀO Ô `products.description`:
 *
 * Cách "dễ" là cho admin dán HTML có <img> vào ô mô tả. Làm vậy nghĩa là:
 *
 *   - phải cho phép thẻ <img src> trong HTML người dùng nhập — mở đúng cánh
 *     cửa mà HtmlSanitizer đang đóng (src có thể là `javascript:`, có thể là
 *     ảnh trên máy chủ người khác để đếm lượt xem của khách);
 *   - ảnh nằm trong chuỗi văn bản nên không ai xoá được file khi sửa mô tả —
 *     đĩa đầy dần bằng ảnh không còn ai trỏ tới;
 *   - không đổi được thứ tự nếu không sửa HTML bằng tay.
 *
 * Mỗi khối một dòng thì: ảnh là một FILE có chủ, xoá khối là xoá được file;
 * chữ vẫn đi qua bộ làm sạch; đổi thứ tự là đổi một con số.
 *
 * `products.description` KHÔNG bị bỏ: sản phẩm chưa có khối nào vẫn hiện mô tả
 * cũ như trước. 52 sản phẩm đang có không phải nhập lại gì.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('sort_order')->default(0);

            // 'text' hoặc 'image' — xem App\Enums\ProductBlockKind.
            $table->string('kind', 10);

            // Khối chữ: đã qua HtmlSanitizer trước khi lưu.
            $table->text('body')->nullable();

            // Khối ảnh: đường dẫn trong đĩa `public`, và lời chú dưới ảnh.
            $table->string('image_path')->nullable();
            $table->string('caption', 255)->nullable();

            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_blocks');
    }
};
