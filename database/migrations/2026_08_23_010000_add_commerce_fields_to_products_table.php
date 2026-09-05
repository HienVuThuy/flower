<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bổ sung nền tảng cho mục 8 (Giá và tồn kho) và 4.4/13
     * (đặc thù chăm sóc hoa/cây) trong bản thiết kế cốt lõi.
     *
     * care_info là JSON vì không phải sản phẩm nào cũng cần cùng
     * bộ thuộc tính chăm sóc (bó hoa khác cây chậu) — tránh tạo
     * hàng chục cột rỗng cho từng loại sản phẩm.
     *
     * track_inventory + stock_quantity chỉ áp dụng khi sản phẩm
     * không có variant; nếu có variant, tồn kho nằm ở variant đó
     * (xem migration product_variants).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'care_info')) {
                $table->json('care_info')->nullable()->after('description');
            }

            if (! Schema::hasColumn('products', 'sale_price')) {
                $table->decimal('sale_price', 14, 2)->nullable()->after('base_price');
                $table->timestamp('sale_starts_at')->nullable()->after('sale_price');
                $table->timestamp('sale_ends_at')->nullable()->after('sale_starts_at');
            }

            if (! Schema::hasColumn('products', 'track_inventory')) {
                $table->boolean('track_inventory')->default(false)->after('status');
                $table->integer('stock_quantity')->nullable()->after('track_inventory');
            }

            if (! Schema::hasColumn('products', 'view_count')) {
                $table->unsignedInteger('view_count')->default(0)->after('stock_quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'care_info',
                'sale_price',
                'sale_starts_at',
                'sale_ends_at',
                'track_inventory',
                'stock_quantity',
                'view_count',
            ]);
        });
    }
};
