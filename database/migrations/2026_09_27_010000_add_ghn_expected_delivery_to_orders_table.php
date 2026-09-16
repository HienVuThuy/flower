<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Khoảng thời gian GHN dự kiến giao hàng. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('ghn_expected_from')->nullable()->after('shipping_status');
            $table->timestamp('ghn_expected_to')->nullable()->after('ghn_expected_from');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['ghn_expected_from', 'ghn_expected_to']);
        });
    }
};
