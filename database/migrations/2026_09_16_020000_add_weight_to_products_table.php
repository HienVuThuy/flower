<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cân nặng sản phẩm — để tính phí giao cho đúng. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('weight')->nullable()->after('stock_quantity');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('weight')->nullable()->after('stock_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('weight'));
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('weight'));
    }
};
