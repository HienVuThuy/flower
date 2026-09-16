<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Làm cho chỉ mục chống trùng dòng giỏ hàng THẬT SỰ chống được trùng. */
return new class extends Migration
{
    public function up(): void
    {
        $trung = DB::table('cart_items')
            ->selectRaw('cart_id, product_id, COALESCE(product_variant_id, 0) as vkey,'
                .' MIN(id) as giu_lai, SUM(quantity) as tong, COUNT(*) as so_dong')
            ->groupBy('cart_id', 'product_id', 'vkey')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($trung as $nhom) {
            DB::table('cart_items')->where('id', $nhom->giu_lai)
                ->update(['quantity' => (int) $nhom->tong]);

            DB::table('cart_items')
                ->where('cart_id', $nhom->cart_id)
                ->where('product_id', $nhom->product_id)
                ->whereRaw('COALESCE(product_variant_id, 0) = ?', [$nhom->vkey])
                ->where('id', '!=', $nhom->giu_lai)
                ->delete();
        }

        Schema::table('cart_items', function (Blueprint $table) {
            $table->index('cart_id', 'cart_items_cart_id_tam');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_unique_line');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->unsignedBigInteger('variant_key')
                ->storedAs('COALESCE(product_variant_id, 0)')
                ->after('product_variant_id');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->unique(['cart_id', 'product_id', 'variant_key'], 'cart_items_unique_line');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex('cart_items_cart_id_tam');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->index('cart_id', 'cart_items_cart_id_tam');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_unique_line');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('variant_key');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->unique(['cart_id', 'product_id', 'product_variant_id'], 'cart_items_unique_line');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex('cart_items_cart_id_tam');
        });
    }
};
