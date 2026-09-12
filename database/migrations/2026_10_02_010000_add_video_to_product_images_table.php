<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thư viện sản phẩm nhận thêm VIDEO, không chỉ ảnh.
 * ============================================================
 * VÌ SAO CHUNG BẢNG `product_images` chứ không lập bảng riêng: người bán xếp
 * ảnh và video vào CÙNG MỘT dải xem, theo cùng một thứ tự. Hai bảng thì thứ
 * tự phải trộn ở tầng PHP mỗi lần hiển thị, và "ảnh thứ 3" với "video thứ 1"
 * không có cách nào nói ai đứng trước.
 *
 * TÊN BẢNG GIỮ NGUYÊN. Đổi tên bảng đang có 100+ dòng dữ liệu và 9 chỗ tham
 * chiếu chỉ để đúng chữ nghĩa là đổi lấy rủi ro không cần thiết; chú thích này
 * là chỗ nói rõ nó nay chứa cả hai.
 *
 * ============================================================
 * HAI KIỂU VIDEO, VÀ VÌ SAO CẦN CẢ HAI:
 *
 *   - `video_url`: link YouTube/Vimeo. Không tốn dung lượng máy chủ, xem mượt,
 *     nhưng phụ thuộc bên thứ ba và kéo theo theo dõi người xem.
 *   - `path` (kind = video): tệp MP4 tải lên. Cửa hàng tự giữ, không ai theo
 *     dõi khách, nhưng tốn chỗ và tốn băng thông.
 *
 * Cửa hàng hoa quay video ngắn bằng điện thoại thì tải thẳng lên tiện hơn;
 * video dựng sẵn đã đăng YouTube thì dán link. Đúng một trong hai cột có giá
 * trị — kiểm ở ProductImage::video().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            /*
             * Mặc định 'image' để 100+ dòng đang có vẫn đúng nghĩa cũ mà không
             * cần cập nhật dữ liệu.
             */
            $table->string('kind', 10)->default('image')->after('product_id');

            // Link nhúng đã chuẩn hoá (youtube-nocookie / player.vimeo).
            $table->string('video_url', 500)->nullable()->after('path');
        });

        /*
         * `path` thành nullable: video dạng link không có tệp nào trên đĩa.
         * Ảnh vẫn bắt buộc có path — ProductImage kiểm khi ghi.
         */
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('path')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Xoá dòng video TRƯỚC khi trả path về NOT NULL, nếu không những dòng
        // link (path = null) làm migration lùi bị nghẽn.
        \Illuminate\Support\Facades\DB::table('product_images')->whereNull('path')->delete();

        Schema::table('product_images', function (Blueprint $table) {
            $table->string('path')->nullable(false)->change();
            $table->dropColumn(['kind', 'video_url']);
        });
    }
};
