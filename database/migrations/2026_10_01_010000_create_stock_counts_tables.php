<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phiếu kiểm kê kho.
 * ============================================================
 * VÌ SAO CẦN, khi đã có phiếu nhập: phiếu nhập ghi hàng ĐI VÀO. Hàng mất đi
 * mà không qua đơn nào — cây chết, chậu vỡ, hoa héo phải bỏ, đếm nhầm lúc
 * nhập — thì không có chứng từ nào ghi. Tồn trên hệ thống cứ thế lệch dần
 * khỏi kệ thật, và trang Tồn kho tính "còn bán được mấy ngày" trên một con số
 * không có thật.
 *
 * Kiểm kê là đi đếm thật rồi ghi chênh lệch — CÓ CHỨNG TỪ, không phải sửa
 * tay ô tồn kho.
 *
 * ============================================================
 * CHỤP TỒN HỆ THỐNG LÚC LẬP PHIẾU, và lúc ghi sổ CỘNG CHÊNH LỆCH chứ không
 * gán số đếm được.
 *
 * Người đếm bắt đầu lúc 8h, ghi sổ lúc 10h. Giữa hai lúc đó có hai đơn bán
 * đi. Gán thẳng số đếm (lúc 8h) vào tồn (lúc 10h) là trả lại hai món đã bán.
 * Cộng CHÊNH LỆCH (đếm được − hệ thống lúc 8h) vào tồn lúc 10h thì đúng:
 * phần đã bán sau lúc đếm vẫn giữ nguyên.
 *
 * `applied_difference` ghi chênh lệch THỰC SỰ đã cộng lúc ghi sổ — chứng từ
 * phải nói nó đã làm gì, không chỉ nói người ta đã đếm được bao nhiêu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->text('note')->nullable();
            $table->date('counted_at');
            $table->string('status', 20)->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

            // Bản chụp tên: sản phẩm đổi tên hay bị xoá, chứng từ cũ vẫn đọc được.
            $table->string('product_name');
            $table->string('variant_name')->nullable();

            $table->integer('system_quantity');
            $table->unsignedInteger('counted_quantity');
            $table->integer('applied_difference')->nullable();

            // Lý do lệch, gõ ngắn: "3 chậu vỡ khi chuyển kệ".
            $table->string('reason', 255)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');
    }
};
