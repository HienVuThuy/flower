<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trả góp TRƯỚC KHI GIAO — kế hoạch trả và từng kỳ.
 * ============================================================
 * Khách đặt đơn, hàng được giữ (trừ kho như đơn thường), khách trả trước
 * một phần rồi trả nốt theo kỳ; cửa hàng CHỈ giao khi đã trả đủ. Không
 * phải cho vay: cửa hàng không giao hàng trước khi có tiền.
 *
 * HAI BẢNG, không phải vài cột trong `orders`: một đơn có nhiều kỳ, mỗi kỳ
 * một hạn, một số tiền, một lúc trả. Đơn thường không có dòng nào ở đây.
 *
 * TIỀN CỦA TỪNG LẦN TRẢ vẫn nằm ở `payment_transactions` — cùng một sổ
 * với đơn thường (MoMo hay thu tại cửa hàng), chỉ thêm cột trỏ tới kỳ.
 * Không dựng sổ tiền thứ hai.
 *
 * KHÔNG lưu "trả trễ" hay "điểm tín dụng hiện tại": cả hai suy ra được từ
 * hạn và lúc trả. Lưu lại là hai nguồn sẽ lệch nhau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            // Xoá tài khoản không xoá chứng từ tiền.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // CHỤP lúc tạo: sửa cấu hình trả góp sau này không đổi kế hoạch đang chạy.
            $table->decimal('total_amount', 14, 2);
            $table->unsignedTinyInteger('down_payment_percent');
            $table->unsignedTinyInteger('period_count');
            $table->unsignedSmallInteger('period_days');
            $table->unsignedSmallInteger('grace_days');
            // Điểm tín dụng lúc xét — để biết vì sao được mức này.
            $table->unsignedTinyInteger('credit_score');

            $table->string('status', 20)->default('dang_tra');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('defaulted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });

        Schema::create('installment_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_plan_id')->constrained()->cascadeOnDelete();
            // 0 = khoản trả trước, 1..n = các kỳ.
            $table->unsignedTinyInteger('sequence');
            $table->decimal('amount', 14, 2);
            // Ngày theo giờ cửa hàng (Asia/Ho_Chi_Minh).
            $table->date('due_on');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['installment_plan_id', 'sequence']);
            $table->index('due_on');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->foreignId('installment_payment_id')->nullable()->after('order_id')
                ->constrained('installment_payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('installment_payment_id');
        });

        Schema::dropIfExists('installment_payments');
        Schema::dropIfExists('installment_plans');
    }
};
