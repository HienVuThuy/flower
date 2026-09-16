<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** CHỤP THUẾ VÀO TỪNG DÒNG HÀNG, không chỉ vào đơn. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('discount_amount', 12, 2)
                ->default(0)
                ->after('line_total');

            $table->decimal('tax_rate', 6, 5)->nullable()->after('discount_amount');
            $table->decimal('tax_amount', 12, 2)->nullable()->after('tax_rate');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('shipping_tax_amount', 12, 2)
                ->nullable()
                ->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_tax_amount');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'tax_rate', 'tax_amount']);
        });
    }
};
