<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cây phân loại sinh học của thực vật.
 * ============================================================
 * VÌ SAO ĐÂY PHẢI LÀ MỘT BẢNG RIÊNG, KHÔNG DÙNG `product_traits`.
 *
 * Mọi tiêu chí phân loại khác của dự án (môi trường sống, dạng sống,
 * dáng, màu) đều là nhãn PHẲNG, và chúng nằm gọn trong `product_traits`
 * mà không cần thêm bảng nào — đó là lý do chúng không có migration.
 *
 * Phân loại sinh học thì khác ở một điểm không thể bỏ qua: **nó là một
 * cây có thứ bậc**. Lưu phẳng thì mỗi sản phẩm phải mang đủ bảy nhãn
 * (Giới, Ngành, Lớp, Bộ, Họ, Chi, Loài), và khi ấy:
 *
 *   1. KHÔNG CÓ GÌ ĐẢM BẢO TÍNH NHẤT QUÁN. Một sản phẩm có thể mang
 *      Chi = Monstera nhưng Họ = Rosaceae. Sai hoàn toàn về sinh học,
 *      mà cơ sở dữ liệu vẫn nhận.
 *
 *   2. SỬA MỘT TÊN LÀ SỬA N SẢN PHẨM. Giới khoa học đổi vị trí phân
 *      loại của một chi là chuyện có thật và xảy ra thường xuyên.
 *
 *   3. KHÔNG DUYỆT NGƯỢC LÊN ĐƯỢC. Câu "cho tôi xem mọi cây thuộc họ
 *      Ráy" phải quét toàn bộ bảng nhãn thay vì đi một nhánh.
 *
 * Cấu trúc cha–con giải quyết cả ba: mỗi nút khai đúng cha của nó, tên
 * nằm ở một chỗ duy nhất, và duyệt cây là đi theo `parent_id`.
 *
 * ============================================================
 * SẢN PHẨM TRỎ VÀO BẬC SÂU NHẤT MÀ CỬA HÀNG BIẾT CHẮC.
 *
 * Không phải cây nào cũng xác định được tới loài. "Sen đá mix" là nhiều
 * loài trong một chậu; "xương rồng bi" là tên chợ, không phải tên loài.
 * Những trường hợp đó trỏ vào Chi hoặc Họ, và đường dẫn phân loại hiện
 * ra ngắn hơn — ĐÚNG hơn là bịa một cái tên loài cho đủ bảy bậc.
 *
 * `taxon_id` để NULL được: hoa cắt cành nhập theo lô, phụ kiện, vật tư
 * đều không có phân loại thực vật. Bắt buộc điền là bắt người nhập liệu
 * bịa ra dữ liệu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_taxa', function (Blueprint $table) {
            $table->id();

            /*
             * Cha trực tiếp. NULL = nút gốc (Giới Thực vật).
             *
             * nullOnDelete chứ không cascadeOnDelete: xoá nhầm một cái Họ
             * mà kéo theo mọi Chi và Loài bên dưới là mất dữ liệu không
             * lấy lại được. Đứt cha thì nhánh đó nổi lên thành gốc — dễ
             * nhìn thấy và sửa lại được.
             */
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('plant_taxa')
                ->nullOnDelete();

            $table->string('rank', 20);

            /** Tên tiếng Việt: "Họ Ráy", "Chi Trầu bà". */
            $table->string('name');

            /** Tên khoa học: "Araceae", "Monstera deliciosa". */
            $table->string('scientific_name')->nullable();

            $table->string('slug')->unique();

            $table->text('description')->nullable();

            /*
             * Ảnh ĐẠI DIỆN cho nhóm này.
             *
             * Không bắt buộc: phần lớn nút chỉ là bậc trung gian (Lớp,
             * Bộ) và không có ảnh nào tả được chúng. Giao diện tự dùng
             * hình lá mặc định khi thiếu.
             */
            $table->string('image')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            /*
             * Truy vấn thường gặp là "các nút con của nút này, theo bậc"
             * — duyệt cây từ trên xuống. Chỉ mục ghép phủ đúng câu đó.
             */
            $table->index(['parent_id', 'rank']);
        });

        Schema::table('products', function (Blueprint $table) {
            /*
             * nullOnDelete: xoá một nút phân loại KHÔNG được kéo theo
             * sản phẩm. Sản phẩm là hàng đang bán và có thể đang nằm
             * trong đơn của khách; một thao tác dọn dẹp danh mục khoa
             * học không có quyền chạm tới nó.
             */
            $table->foreignId('taxon_id')
                ->nullable()
                ->after('category_id')
                ->constrained('plant_taxa')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['taxon_id']);
            $table->dropColumn('taxon_id');
        });

        Schema::dropIfExists('plant_taxa');
    }
};
