<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hai cột chỉ mục tìm kiếm cho sản phẩm.
     * ============================================================
     * VÌ SAO PHẢI LÀ CỘT THẬT, KHÔNG TÍNH LÚC TRUY VẤN:
     * Muốn khách gõ "hoa hong" vẫn ra "Hoa hồng đỏ Ecuador" thì phải so
     * khớp trên bản đã bỏ dấu. Bỏ dấu ngay trong câu SQL nghĩa là gọi hàm
     * lên TỪNG DÒNG mỗi lần tìm — cơ sở dữ liệu không dùng được chỉ mục
     * nào nữa, và MariaDB 10.4 cũng không có chỉ mục theo biểu thức để
     * cứu. Tính sẵn một lần lúc lưu sản phẩm là rẻ hơn hẳn: sản phẩm được
     * đọc hàng nghìn lần cho mỗi lần được sửa.
     *
     * VÌ SAO TÁCH LÀM HAI CỘT, KHÔNG GỘP MỘT:
     *   - search_name: chỉ tên sản phẩm. Dùng để XẾP HẠNG. Gõ "hoa hồng"
     *     thì "Hoa hồng đỏ Ecuador" phải đứng trên "Giỏ hoa baby trắng";
     *     phân biệt được điều đó thì phải biết từ khoá nằm trong TÊN hay
     *     chỉ nằm đâu đó trong mô tả.
     *   - search_text: tên + mô tả ngắn + tên danh mục + nhãn hình thức
     *     bán. Dùng để TÌM RA. Khách gõ "cây để bàn" hay "bó hoa" là đang
     *     gõ tên danh mục / hình thức chứ không phải tên sản phẩm.
     * Gộp một cột thì mất khả năng xếp hạng; bỏ cột thứ hai thì mất một
     * nửa số cách khách thật sự gõ.
     *
     * Cả hai cột đều CÓ THỂ DỰNG LẠI HOÀN TOÀN từ dữ liệu gốc bằng
     * `php artisan search:reindex`, nên không phải nguồn sự thật, không
     * có nguy cơ lệch dữ liệu vĩnh viễn.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('search_name', 255)->nullable()->after('slug');
            $table->string('search_text', 1000)->nullable()->after('search_name');

            /*
             * Chỉ mục chỉ trên search_name.
             *
             * Nó giúp được các trường hợp so bằng và so tiền tố
             * (LIKE 'hoa%') — tức là gõ đúng từ đầu, trường hợp phổ biến
             * nhất. Kiểu tìm LIKE '%hoa%' thì không chỉ mục B-tree nào
             * dùng được, chấp nhận quét bảng.
             *
             * KHÔNG đánh chỉ mục search_text: cột đó luôn được tìm kiểu
             * '%...%' nên chỉ mục sẽ không bao giờ được dùng, chỉ tốn chỗ
             * và làm chậm mọi lần ghi. Xem thêm phần "Rủi ro" trong báo
             * cáo: khi catalog lên hàng chục nghìn sản phẩm thì lời giải
             * đúng là FULLTEXT hoặc máy tìm kiếm riêng, không phải thêm
             * chỉ mục ở đây.
             */
            $table->index('search_name', 'products_search_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_search_name_index');
            $table->dropColumn(['search_name', 'search_text']);
        });
    }
};
