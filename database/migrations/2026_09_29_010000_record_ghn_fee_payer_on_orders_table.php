<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Cước GHN: ai trả, và "chưa có số liệu" khác 0₫. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('ghn_fee_payer', 10)->nullable()->after('ghn_total_fee');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->integer('ghn_total_fee')->nullable()->default(null)->change();
        });

        DB::table('orders')->where('ghn_total_fee', 0)->update(['ghn_total_fee' => null]);

        DB::table('orders')
            ->whereNotNull('ghn_order_code')
            ->update(['ghn_fee_payer' => 'buyer']);
    }

    public function down(): void
    {
        DB::table('orders')->whereNull('ghn_total_fee')->update(['ghn_total_fee' => 0]);

        Schema::table('orders', function (Blueprint $table) {
            $table->integer('ghn_total_fee')->nullable(false)->default(0)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('ghn_fee_payer');
        });
    }
};
