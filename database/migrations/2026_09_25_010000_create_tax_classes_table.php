<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NHÓM THUẾ SUẤT — thay cho "một mức thuế cho toàn cửa hàng".
 * ============================================================
 * VÌ SAO CẦN, KHI ĐÃ CÓ Setting `tax_rate`:
 *
 * Cửa hàng này bán ba loại hàng có bản chất thuế KHÁC HẲN nhau:
 *
 *   - hoa tươi, cây giống  — nông sản; có trường hợp không chịu VAT
 *   - chậu, giá thể, phân  — hàng hoá thông thường
 *   - dịch vụ chăm cây     — dịch vụ
 *
 * Một con số duy nhất trong Setting không diễn tả được điều đó. Nó ép cả
 * ba loại vào cùng một mức, và mức nào cũng sai với hai loại còn lại.
 *
 * ============================================================
 * `rate` NULL KHÁC `rate` = 0.
 *
 *   0       = "chịu thuế suất 0%"  (ví dụ hàng xuất khẩu đủ điều kiện)
 *   NULL    = "không thuộc đối tượng chịu VAT"
 *
 * Hai thứ này khác nhau về nghiệp vụ và về cách ghi trên hoá đơn, nên
 * gộp lại là làm mất khả năng phân biệt. Cùng nguyên tắc đã dùng cho
 * `orders.tax_amount` (xem 2026_09_20_010000).
 *
 * ============================================================
 * BẢNG NÀY CHỈ LÀ CẤU HÌNH, KHÔNG PHẢI LỜI TƯ VẤN THUẾ.
 *
 * Tạo sẵn vài dòng KHÔNG có nghĩa là sản phẩm nào cũng tự động được áp
 * mức đó. Việc phân loại phải căn cứ mặt hàng thực tế và quy định áp
 * dụng tại thời điểm bán — kế toán của cửa hàng quyết định, không phải
 * mã nguồn. Vì thế `products.tax_class_id` mặc định để NULL: "chưa phân
 * loại, dùng mức mặc định của cửa hàng".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_classes', function (Blueprint $table) {
            $table->id();

            // Tên người đọc thấy ở trang quản trị: "VAT 8%".
            $table->string('name', 100);

            /*
             * Mã bền để mã nguồn tham chiếu.
             *
             * Tên hiển thị đổi được ("VAT 8%" thành "GTGT 8%") mà không
             * làm hỏng chỗ nào; mã thì không đổi. Seeder dùng mã này để
             * cập nhật thay vì tạo trùng.
             */
            $table->string('code', 40)->unique();

            /*
             * Thuế suất dạng thập phân: 0.08000 = 8%.
             *
             * 5 chữ số thập phân, khớp với `orders.tax_rate` — hai cột
             * này được so sánh và sao chép cho nhau, nên phải cùng độ
             * chính xác. Lệch scale là chỗ sinh sai số âm thầm.
             */
            $table->decimal('rate', 6, 5)->nullable();

            /*
             * GHI LẠI CĂN CỨ, ngay cạnh con số.
             *
             * Sáu tháng sau không ai nhớ vì sao chậu sứ để 10% còn hoa
             * tươi để trống. Bắt buộc thì phiền, nhưng có chỗ để ghi thì
             * người cấu hình còn có cơ hội ghi.
             */
            $table->string('note', 300)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            /*
             * NULL = "chưa phân loại, dùng mức mặc định của cửa hàng".
             *
             * Mặc định NULL cho MỌI sản phẩm hiện có là điều kiện để
             * thay đổi này không làm lệch một đồng nào trong đơn đã có:
             * hành vi cũ (một mức cho tất cả) chính là trường hợp NULL.
             */
            $table->foreignId('tax_class_id')
                ->nullable()
                ->after('sale_ends_at')
                ->constrained('tax_classes')
                /*
                 * Xoá nhóm thuế thì sản phẩm rơi về mức mặc định, KHÔNG
                 * xoá theo sản phẩm. Một dòng cấu hình bị xoá nhầm không
                 * được phép kéo theo hàng hoá.
                 */
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['tax_class_id']);
            $table->dropColumn('tax_class_id');
        });

        Schema::dropIfExists('tax_classes');
    }
};
