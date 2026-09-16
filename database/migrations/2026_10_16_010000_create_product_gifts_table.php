<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Quà tặng kèm SẢN PHẨM — quà mặc định của chính món hàng. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_gifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gift_item_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('per_quantity')->default(1);
            $table->unsignedSmallInteger('gift_quantity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'gift_item_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('parent_item_id')->nullable()->after('gift_item_id')->constrained('order_items')->nullOnDelete();
            $table->foreignId('product_gift_id')->nullable()->after('parent_item_id')->constrained()->nullOnDelete();
        });

        $bayGio = now();

        foreach (DB::table('gift_campaigns')->where('kind', 'kem_san_pham')->whereNotNull('trigger_product_id')->get() as $cu) {
            DB::table('product_gifts')->updateOrInsert(
                ['product_id' => $cu->trigger_product_id, 'gift_item_id' => $cu->gift_item_id],
                [
                    'per_quantity' => max(1, (int) $cu->trigger_min_quantity),
                    'gift_quantity' => max(1, (int) $cu->gift_quantity),
                    'is_active' => $cu->status === 'active',
                    'created_at' => $bayGio,
                    'updated_at' => $bayGio,
                ],
            );

            if ((int) $cu->used_count > 0) {
                DB::table('gift_campaigns')->where('id', $cu->id)->update(['status' => 'ended', 'updated_at' => $bayGio]);
            } else {
                DB::table('gift_campaigns')->where('id', $cu->id)->delete();
            }
        }
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_gift_id');
            $table->dropConstrainedForeignId('parent_item_id');
        });

        Schema::dropIfExists('product_gifts');
    }
};
