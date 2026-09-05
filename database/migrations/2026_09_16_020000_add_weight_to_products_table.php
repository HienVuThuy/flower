<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cân nặng sản phẩm — để tính phí giao cho đúng.
 * ============================================================
 * NGHIỆP VỤ CỤ THỂ, không phải "cột nào cũng nên có":
 *
 * GHN tính phí theo CÂN NẶNG. Một bó hoa baby và một chậu bonsai tùng
 * la hán chênh nhau cả chục lần, nên dùng chung một con số mặc định thì
 * hoặc cửa hàng bù lỗ cho hàng nặng, hoặc thu thừa của khách mua hàng
 * nhẹ. Cả hai đều sai, và cái sai thứ nhất chỉ lộ ra khi đối soát cuối
 * tháng.
 *
 * ĐƠN VỊ LÀ GRAM, KHÔNG PHẢI KILOGRAM. Đó là đơn vị GHN dùng, và đổi
 * đơn vị ở giữa đường là chỗ để sai một dấu phẩy thành gấp nghìn lần.
 * Kiểu nguyên vì gram đã đủ mịn — không ai cân hoa tới phần nghìn gram.
 *
 * nullable, KHÔNG mặc định 200: null nghĩa là "cửa hàng chưa khai", và
 * đó là thông tin có ích — trang quản trị nhắc được. Đặt cứng 200 thì
 * một chậu 5kg chưa khai trông y hệt một bó hoa đã khai đúng 200g.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('weight')->nullable()->after('stock_quantity');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            /*
             * Quy cách cũng có cân nặng riêng: "Chậu đá 12cm" và "Chậu
             * đá 18cm" là hai khối lượng khác nhau. Để trống thì lấy
             * theo sản phẩm cha — xem Product::shippingWeight().
             */
            $table->unsignedInteger('weight')->nullable()->after('stock_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('weight'));
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('weight'));
    }
};
