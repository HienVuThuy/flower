<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Luật RIÊNG của từng món quà kèm sản phẩm — admin sửa được, không khoá cứng. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_gifts', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')
                ->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedSmallInteger('max_quantity')->nullable()->after('gift_quantity');
            $table->string('khi_thieu_kho', 20)->default('tang_phan_con')->after('max_quantity');
            $table->string('tra_hang', 20)->default('kem_qua')->after('khi_thieu_kho');
            $table->boolean('cho_doi_hang')->default(false)->after('tra_hang');

            $table->index('product_id', 'product_gifts_product_id_index');
        });

        Schema::table('product_gifts', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'gift_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('product_gifts', function (Blueprint $table) {
            $table->unique(['product_id', 'gift_item_id']);
        });

        Schema::table('product_gifts', function (Blueprint $table) {
            $table->dropIndex('product_gifts_product_id_index');
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn(['max_quantity', 'khi_thieu_kho', 'tra_hang', 'cho_doi_hang']);
        });
    }
};
