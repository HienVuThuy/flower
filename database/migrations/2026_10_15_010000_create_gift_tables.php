<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quà tặng — hai nhánh trong MỘT mô hình.
 * ============================================================
 * gift_items — THỨ được tặng. Hai loại nguồn:
 *   - trỏ tới một SẢN PHẨM đang có (cây, chậu, phân bón): tồn kho dùng
 *     chung với sản phẩm — tặng một chậu là kho bớt một chậu, không có
 *     hai con số tồn cho cùng một món;
 *   - VẬT PHẨM TẶNG RIÊNG, không bán (túi vải, thẻ chăm cây, gói hạt
 *     giống): tồn kho riêng ở chính dòng này.
 *
 * gift_campaigns — KHI NÀO tặng:
 *   - kem_san_pham: mua sản phẩm X (đủ số lượng) thì kèm quà — nhánh 1,
 *     như các sàn thương mại điện tử;
 *   - chuong_trinh: quà giới hạn số lượng, thời gian, hạng thành viên, đơn
 *     đầu tiên, đơn từ X đồng — nhánh 2.
 *   Hai nhánh chung một bảng vì chung gần hết điều kiện (thời gian, số
 *   lượng, hạng); tách hai bảng là hai bản của cùng luật.
 *
 * order_items.is_gift / gift_campaign_id / gift_item_id — quà là một DÒNG
 * trong đơn, giá 0đ: kho, huỷ đơn, in phiếu soạn hàng đi qua đúng đường
 * của hàng bán, và "khách đã nhận quà này mấy lần" đếm được từ đơn thật.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('kind', 20);

            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

            // Chỉ dùng khi KHÔNG trỏ sản phẩm. NULL ở vật phẩm riêng không được phép (kiểm ở tầng ứng dụng).
            $table->unsignedInteger('stock_quantity')->nullable();

            // Trị giá tham khảo do cửa hàng nhập — để khách biết món quà đáng bao nhiêu. NULL = không ghi.
            $table->decimal('value', 12, 2)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('gift_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('kind', 20);

            $table->foreignId('gift_item_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('gift_quantity')->default(1);

            // Nhánh "kèm sản phẩm".
            $table->foreignId('trigger_product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->unsignedSmallInteger('trigger_min_quantity')->default(1);

            // Điều kiện chung.
            $table->decimal('min_order_amount', 12, 2)->nullable();
            $table->foreignId('min_member_tier_id')->nullable()->constrained('member_tiers')->nullOnDelete();
            $table->boolean('first_order_only')->default(false);
            $table->unsignedSmallInteger('per_user_limit')->nullable();

            // Tổng suất quà; tăng bằng UPDATE có điều kiện, cùng cách với lượt mã giảm giá.
            $table->unsignedInteger('total_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 20)->default('draft');

            $table->timestamps();

            $table->index(['status', 'kind']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('is_gift')->default(false)->after('line_total');
            $table->foreignId('gift_campaign_id')->nullable()->after('is_gift')->constrained()->nullOnDelete();
            $table->foreignId('gift_item_id')->nullable()->after('gift_campaign_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gift_item_id');
            $table->dropConstrainedForeignId('gift_campaign_id');
            $table->dropColumn('is_gift');
        });

        Schema::dropIfExists('gift_campaigns');
        Schema::dropIfExists('gift_items');
    }
};
