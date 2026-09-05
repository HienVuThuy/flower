<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tách HÀNG CHÍNH khỏi HÀNG PHỤ TRỢ.
     * ============================================================
     * Xem App\Enums\CategoryKind để biết vì sao trục này không trùng với
     * category / selling_form / product_type.
     *
     * MẶC ĐỊNH 'plant', và đó là lựa chọn có cân nhắc: mọi danh mục đang
     * có trước migration này đều là hoa và cây cảnh — trừ hai danh mục
     * phụ kiện/vật tư mới thêm, được đánh dấu ngay ở bước dưới. Mặc định
     * ngược lại thì toàn bộ catalog biến mất khỏi trang chủ cho tới khi
     * ai đó sửa tay từng danh mục.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('kind', 20)->default('plant')->after('slug');

            /*
             * Chỉ mục: gần như MỌI truy vấn danh mục từ nay đều lọc theo
             * cột này (trang chủ, trang sản phẩm, trang phụ kiện, gợi ý).
             */
            $table->index('kind', 'categories_kind_index');
        });

        /*
         * Đánh dấu hai danh mục phụ trợ đã tồn tại.
         *
         * Dùng slug chứ không dùng id: id khác nhau giữa các máy, còn
         * slug do PlantAdvisorSeeder đặt cố định. Không có dòng nào khớp
         * (cơ sở dữ liệu mới, chưa seed) thì câu lệnh chạy không lỗi và
         * seeder sẽ tự đặt đúng kind khi chạy sau.
         */
        \Illuminate\Support\Facades\DB::table('categories')
            ->whereIn('slug', ['phu-kien', 'vat-tu-cham-soc'])
            ->update(['kind' => 'supply']);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_kind_index');
            $table->dropColumn('kind');
        });
    }
};
