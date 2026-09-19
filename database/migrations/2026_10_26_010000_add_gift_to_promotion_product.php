<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Quà riêng cho từng sản phẩm trong chương trình khuyến mại. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotion_product', function (Blueprint $table) {
            $table->foreignId('gift_item_id')->nullable()->after('promotional_price')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('gift_quantity')->nullable()->after('gift_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('promotion_product', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gift_item_id');
            $table->dropColumn('gift_quantity');
        });
    }
};
