<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Mã đơn để khách và nhân viên gọi tên nhau qua điện thoại.
            $table->string('order_number', 32)->unique();

            // Cho phép đặt hàng không cần tài khoản.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            /* ---------- Người nhận (bước 1 của thanh toán) ---------- */
            $table->string('recipient_name');
            $table->string('recipient_phone', 20);
            $table->string('recipient_email')->nullable();

            $table->string('shipping_address');
            $table->string('shipping_ward')->nullable();
            $table->string('shipping_district')->nullable();
            $table->string('shipping_province');

            $table->date('delivery_date')->nullable();
            $table->text('delivery_note')->nullable();

            /* ---------- Thanh toán (bước 2) ---------- */
            $table->string('payment_method', 32);
            $table->string('payment_status', 20)->default('unpaid');

            /* ---------- Tiền ----------
             * decimal chứ KHÔNG float: float làm tròn nhị phân sẽ lệch
             * tiền. Mọi phép tính tiền trong dự án dùng bcmath.
             */
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);

            /* ---------- Trạng thái ---------- */
            $table->string('status', 20)->default('pending');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();

            $table->text('admin_note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Hai truy vấn hay dùng nhất ở admin: lọc theo trạng thái, và
            // xem đơn mới nhất.
            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
