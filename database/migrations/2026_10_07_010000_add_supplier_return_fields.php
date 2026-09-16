<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** TRẢ HÀNG CHO NHÀ CUNG CẤP — hàng hỏng, giao sai, không đạt. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->string('return_reason', 32)->nullable()->after('note');
            $table->string('settlement', 32)->nullable()->after('return_reason');

            $table->decimal('settlement_amount', 12, 2)->nullable()->after('settlement');

            $table->foreignId('return_of_id')->nullable()->after('settlement_amount')
                ->constrained('stock_receipts')->nullOnDelete();
        });

        Schema::table('flower_lots', function (Blueprint $table) {
            $table->decimal('tra_lai_qty', 12, 2)->nullable()->after('hao_hut');
            $table->decimal('tra_lai_tien', 12, 2)->nullable()->after('tra_lai_qty');
            $table->string('tra_lai_ly_do', 32)->nullable()->after('tra_lai_tien');
            $table->string('tra_lai_settlement', 32)->nullable()->after('tra_lai_ly_do');
            $table->timestamp('tra_lai_at')->nullable()->after('tra_lai_settlement');
        });
    }

    public function down(): void
    {
        Schema::table('flower_lots', function (Blueprint $table) {
            $table->dropColumn([
                'tra_lai_qty', 'tra_lai_tien', 'tra_lai_ly_do',
                'tra_lai_settlement', 'tra_lai_at',
            ]);
        });

        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('return_of_id');
            $table->dropColumn(['return_reason', 'settlement', 'settlement_amount']);
        });
    }
};
