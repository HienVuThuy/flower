<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chăm cây hộ, phần tiền và phiếu tại quầy:
 * - mỗi lần thu / trả lại tiền là một dòng (có ngày, cách trả) để báo cáo thu chi đếm được;
 * - phiếu lập tại quầy cho khách không có tài khoản (tên khách ghi thẳng trên phiếu);
 * - giao dịch MoMo gắn được với phiếu chăm hộ, không chỉ với đơn hàng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boarding_bookings', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('customer_name', 120)->nullable()->after('user_id');
            $table->string('source', 20)->default('online')->after('customer_name');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->change();
            $table->foreignId('boarding_booking_id')->nullable()->after('installment_payment_id')
                ->constrained()->cascadeOnDelete();
        });

        Schema::create('boarding_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boarding_booking_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);
            $table->string('note', 200)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index('paid_at');
        });

        foreach (DB::table('boarding_bookings')->where('paid_amount', '!=', 0)->get(['id', 'paid_amount', 'paid_at', 'updated_at']) as $p) {
            DB::table('boarding_payments')->insert([
                'boarding_booking_id' => $p->id,
                'amount' => $p->paid_amount,
                'method' => 'tien_mat',
                'note' => 'Chuyển từ số đã thu trước khi có sổ thu',
                'paid_at' => $p->paid_at ?? $p->updated_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('boarding_payments');

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('boarding_booking_id');
        });

        Schema::table('boarding_bookings', function (Blueprint $table) {
            $table->dropColumn(['customer_name', 'source']);
        });
    }
};
