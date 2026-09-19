<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Giá sale riêng trên sản phẩm chưa từng được dùng: giảm giá đi qua Chương trình khuyến mại. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'sale_price')) {
            return;
        }

        if (DB::table('products')->whereNotNull('sale_price')->exists()) {
            throw new RuntimeException('Còn sản phẩm có sale_price — chuyển sang chương trình khuyến mại trước khi bỏ cột.');
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sale_price', 'sale_starts_at', 'sale_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('sale_price', 14, 2)->nullable()->after('base_price');
            $table->timestamp('sale_starts_at')->nullable()->after('sale_price');
            $table->timestamp('sale_ends_at')->nullable()->after('sale_starts_at');
        });
    }
};
