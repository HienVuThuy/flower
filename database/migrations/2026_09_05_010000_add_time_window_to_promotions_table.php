<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->time('daily_start_time')->nullable()->after('ends_at');
            $table->time('daily_end_time')->nullable()->after('daily_start_time');
            $table->json('weekdays')->nullable()->after('daily_end_time');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['daily_start_time', 'daily_end_time', 'weekdays']);
        });
    }
};
