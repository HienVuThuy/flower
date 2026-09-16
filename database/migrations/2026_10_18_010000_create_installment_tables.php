<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Trả góp TRƯỚC KHI GIAO — kế hoạch trả và từng kỳ. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('total_amount', 14, 2);
            $table->unsignedTinyInteger('down_payment_percent');
            $table->unsignedTinyInteger('period_count');
            $table->unsignedSmallInteger('period_days');
            $table->unsignedSmallInteger('grace_days');
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
            $table->unsignedTinyInteger('sequence');
            $table->decimal('amount', 14, 2);
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
