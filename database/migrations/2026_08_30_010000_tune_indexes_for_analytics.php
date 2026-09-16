<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_events', function (Blueprint $table) {
            $table->index(['event_type', 'created_at'], 'user_events_type_time_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_product_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('user_events', function (Blueprint $table) {
            $table->dropIndex('user_events_type_time_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('product_type', 'products_product_type_index');
        });
    }
};
