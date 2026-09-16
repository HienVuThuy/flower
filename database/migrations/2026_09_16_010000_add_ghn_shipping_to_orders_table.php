<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nối đơn hàng với Giao Hàng Nhanh. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('ghn_order_code')->nullable()->index()->after('shipping_fee');

            $table->integer('ghn_total_fee')->default(0)->after('ghn_order_code');

            $table->integer('to_district_id')->nullable()->after('ghn_total_fee');
            $table->string('to_ward_code', 20)->nullable()->after('to_district_id');

            $table->string('shipping_status', 30)->default('not_shipped')->after('to_ward_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['ghn_order_code']);
            $table->dropColumn([
                'ghn_order_code',
                'ghn_total_fee',
                'to_district_id',
                'to_ward_code',
                'shipping_status',
            ]);
        });
    }
};
