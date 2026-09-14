<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chi phí vận hành — những khoản tiền ra mà hệ thống chưa có số.
 * ============================================================
 * VÌ SAO CẦN BẢNG NÀY: doanh thu, giá vốn, tiền thu mua, hoàn tiền, cước
 * GHN đã có trong hệ thống. Nhưng lương nhân viên, tiền mặt bằng, điện
 * nước, server, thiết bị, túi giấy gói hoa… thì không ở đâu cả. Thiếu
 * chúng, "lãi gộp" trên trang Lợi nhuận trông như tiền cửa hàng làm ra —
 * mà không phải.
 *
 * KHÔNG GHI Ở ĐÂY: tiền mua hàng để bán (đã ở phiếu nhập và lô hoa) — ghi
 * hai lần là trừ chi phí hai lần. Xem ExpenseCategory::VatTu.
 *
 * `is_fixed`: khoản lặp lại mỗi tháng (lương, mặt bằng, server). Có nút
 * "chép các khoản cố định của tháng trước" thay vì một bộ lập lịch tự sinh
 * dòng — tiền lương tháng này có thể khác tháng trước, và một dòng tự sinh
 * mà không ai nhìn là một con số không ai chịu trách nhiệm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            // Ngày chi theo lịch ở cửa hàng — một ngày, không phải một thời điểm.
            $table->date('spent_on');

            $table->string('category', 30);
            $table->string('description', 200);

            // Tiền đồng, không số lẻ; > 0 kiểm ở tầng ứng dụng.
            $table->decimal('amount', 14, 2);

            $table->string('payment_method', 20)->nullable();
            $table->boolean('is_fixed')->default(false);
            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_name')->nullable();

            $table->timestamps();

            $table->index(['spent_on', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
