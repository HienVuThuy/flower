<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('risk_score')->default(0)->after('payment_status');
            $table->json('risk_flags')->nullable()->after('risk_score');

            $table->index('risk_score', 'orders_risk_score_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_risk_score_index');
            $table->dropColumn(['risk_score', 'risk_flags']);
        });
    }
};
