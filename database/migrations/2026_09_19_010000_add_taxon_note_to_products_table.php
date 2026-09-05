<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ghi chú vì sao đường dẫn phân loại của sản phẩm này DỪNG SỚM.
 * ============================================================
 * VẤN ĐỀ: 27/33 cây được gán phân loại, nhưng nhiều cây dừng ở bậc Chi
 * hoặc Họ chứ không xuống tới Loài — vì hàng thương mại là giống lai,
 * vì một chậu có nhiều loài, hoặc vì tên gọi ngoài chợ không ứng với
 * một loài xác định.
 *
 * Đó là dữ liệu ĐÚNG, nhưng trên màn hình nó trông y hệt dữ liệu THIẾU.
 * Khách nhìn thấy chuỗi Giới → ... → Chi rồi hết, và kết luận là cửa
 * hàng làm ẩu.
 *
 * ============================================================
 * VÌ SAO ĐẶT Ở SẢN PHẨM CHỨ KHÔNG ĐẶT Ở NÚT PHÂN LOẠI.
 *
 * Lý do dừng là chuyện của MÓN HÀNG, không phải của nhóm sinh học:
 *
 *   - "Sen đá mix chậu đá" dừng ở họ Thuốc bỏng vì CHẬU ĐÓ có nhiều
 *     loài — nhưng "Sen đá nâu chậu sứ mini" cũng dừng ở đúng họ đó vì
 *     một lý do khác hẳn (giống lai không rõ nguồn).
 *   - Cùng một chi Hoa hồng: có món là giống lai, có món về sau có thể
 *     xác định được loài.
 *
 * Ghi lên nút phân loại thì mọi sản phẩm dưới nút đó phải chung một lời
 * giải thích, và lời đó sai với ít nhất một trong số chúng.
 *
 * NULL là bình thường: cây đã xuống tới bậc Loài thì không có gì phải
 * giải thích.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('taxon_note', 300)->nullable()->after('taxon_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('taxon_note');
        });
    }
};
