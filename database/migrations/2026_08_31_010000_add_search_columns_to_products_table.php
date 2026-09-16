<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('search_name', 255)->nullable()->after('slug');
            $table->string('search_text', 1000)->nullable()->after('search_name');

            $table->index('search_name', 'products_search_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_search_name_index');
            $table->dropColumn(['search_name', 'search_text']);
        });
    }
};
