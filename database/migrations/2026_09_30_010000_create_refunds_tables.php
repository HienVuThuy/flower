<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hoàn tiền và hàng trả về.
 * ============================================================
 * VÌ SAO CẦN BẢNG, khi đã có `orders.payment_status = refunded`:
 *
 * Trước đây "hoàn tiền" là MỘT CÚ BẤM đổi trạng thái. Sau cú bấm đó
 * không ai trả lời được:
 *
 *   - hoàn BAO NHIÊU? Luôn ngầm hiểu là toàn bộ. Bó hoa héo một nửa, cửa
 *     hàng hoàn 30% — không có chỗ ghi.
 *   - hoàn BẰNG CÁCH NÀO, mã giao dịch gì? Khách gọi báo "chưa nhận được"
 *     thì không có gì để tra.
 *   - AI hoàn, LÚC NÀO?
 *   - HÀNG CÓ VỀ KHO KHÔNG? Khách trả chậu sứ còn nguyên, tồn kho vẫn
 *     thiếu một cái.
 *
 * Và vì chỉ đơn ĐÃ HUỶ mới đổi được sang "đã hoàn tiền", đơn đã giao mà
 * hàng hỏng thì KHÔNG hoàn được bằng bất cứ cách nào trong hệ thống.
 *
 * ============================================================
 * 1-N VỚI ĐƠN. Một đơn có thể hoàn nhiều lần: hoàn một phần vì một chậu
 * vỡ, tuần sau hoàn tiếp vì cây thứ hai chết. Tổng các lần không được
 * vượt số khách đã trả — kiểm ở RefundService, dưới khoá dòng.
 *
 * `restrictOnDelete` cho khoá tới đơn: đây là chứng từ tiền. Không được
 * có đường nào xoá đơn mà mất luôn dấu vết đã trả lại khách bao nhiêu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();

            // HT-260930-A3F2: đọc được qua điện thoại khi khách gọi hỏi.
            $table->string('code', 20)->unique();

            $table->decimal('amount', 14, 2);
            $table->string('reason', 20);
            $table->text('note')->nullable();
            $table->string('method', 20);
            $table->string('status', 20)->default('pending');

            // Mã giao dịch ngân hàng, hoặc mã giao dịch hoàn tiền MoMo trả về.
            $table->string('reference', 100)->nullable();

            /*
             * Mã yêu cầu gửi MoMo. UNIQUE vì MoMo từ chối nhận lại một mã
             * đã dùng, và đây là khoá để đối soát một lần hoàn "chưa rõ
             * kết quả" trên cổng MoMo.
             */
            $table->string('gateway_request_id', 100)->nullable()->unique();
            $table->json('gateway_response')->nullable();

            $table->timestamp('completed_at')->nullable();

            // Chụp cả tên: xoá tài khoản thì khoá ngoại thành NULL.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_name')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'status']);
        });

        /*
         * HÀNG KHÁCH TRẢ VỀ, theo từng dòng của đơn.
         *
         * Chỉ có khi đơn ĐÃ GIAO — hàng của đơn huỷ chưa rời cửa hàng, và
         * kho đã được cộng lại lúc huỷ.
         *
         * `restock` tách riêng khỏi `quantity`: chậu vỡ khách gửi về vẫn
         * là hàng trả về (không hoàn quá số đã mua), nhưng không bán lại
         * được nên KHÔNG cộng vào kho.
         */
        Schema::create('refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->boolean('restock')->default(false);
            $table->timestamps();

            $table->unique(['refund_id', 'order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_items');
        Schema::dropIfExists('refunds');
    }
};
