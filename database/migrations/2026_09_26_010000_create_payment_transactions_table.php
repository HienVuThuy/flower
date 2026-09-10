<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhật ký từng LẦN THỬ thanh toán của một đơn.
 *
 * Một đơn có thể trả hụt vài lần rồi mới xong, nên quan hệ là 1-N chứ
 * không phải mấy cột thêm vào `orders`. Cột `request_payload` và
 * `response_payload` giữ gói tin thô để đối soát khi khách báo đã bị trừ
 * tiền mà đơn chưa ghi nhận.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('gateway', 32);

            // Mã lượt thanh toán gửi sang cổng; cũng là khoá để tìm lại
            // đúng lượt đó khi cổng gọi ngược về.
            $table->string('gateway_order_id', 100)->nullable()->index();

            // Mã giao dịch do cổng cấp sau khi tiền về.
            $table->string('transaction_id', 100)->nullable()->index();

            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending');
            $table->integer('result_code')->nullable();
            $table->string('message', 255)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            // Chặn ghi hai lần cùng một lượt khi callback và IPN về gần
            // như đồng thời.
            $table->unique(['gateway', 'gateway_order_id']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
