<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Luật RIÊNG của từng món quà kèm sản phẩm — admin sửa được, không khoá cứng.
 * ============================================================
 * product_variant_id — quy cách của sản phẩm chính kích hoạt quà. NULL =
 *   mọi quy cách. Quy cách bị xoá thì cấu hình quà đó đi theo (cascade):
 *   quà gắn vào một quy cách không còn tồn tại là quà không ai nhận được.
 *
 * max_quantity — tối đa bao nhiêu quà mỗi đơn. NULL = không giới hạn.
 *
 * khi_thieu_kho — quà còn ít hơn số được tặng: tặng phần còn lại, hay không
 *   tặng. Mỗi món một kiểu: túi phân bón thiếu thì tặng bớt được, bộ quà
 *   đôi thiếu một nửa thì thà không tặng.
 *
 * tra_hang — khách trả món chính: phiếu trả tự điền quà cần trả kèm, hay
 *   không thu hồi quà (sticker, thiệp không ai đòi lại). Chỉ là giá trị
 *   ĐIỀN SẴN — người lập phiếu vẫn sửa được số lượng trên từng phiếu.
 *
 * cho_doi_hang — dòng quà có được đổi sang hàng khác không. Mặc định không:
 *   quà 0đ đổi lấy hàng có giá là lỗ hổng, nhưng cửa hàng có thể cho đổi
 *   một cây quà bị héo.
 *
 * Bỏ ràng buộc UNIQUE (product_id, gift_item_id): nay cùng một quà có thể
 * gắn cho hai quy cách khác nhau. Không trùng (sản phẩm, quy cách, quà) kiểm
 * ở tầng ứng dụng — UNIQUE của MySQL coi hai NULL là khác nhau nên không
 * chặn được "mọi quy cách" bị gắn hai lần. Thêm chỉ mục thường cho
 * product_id TRƯỚC khi bỏ UNIQUE: khoá ngoại cần một chỉ mục đứng đầu cột đó.
 */
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
