<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Khoá chống đặt trùng đơn.
     * ============================================================
     * VẤN ĐỀ CÓ THẬT: khách bấm "Đặt hàng" hai lần, hoặc bấm xong rồi
     * bấm Quay lại và gửi lại biểu mẫu. Không chặn thì cửa hàng có hai
     * đơn giống hệt nhau, kho bị trừ hai lần, và khách bị tính tiền đôi.
     *
     * VÌ SAO ĐẶT CỘT NGAY TRÊN BẢNG orders, KHÔNG TẠO BẢNG RIÊNG:
     * Khoá này chỉ có ý nghĩa gắn với một đơn hàng cụ thể. Tách ra bảng
     * riêng thì phải tự đồng bộ hai bảng, mà đồng bộ hai bảng chính là
     * chỗ sinh ra lỗi kiểu "khoá đã ghi nhưng đơn chưa tạo". Để cùng một
     * hàng thì việc ghi khoá và việc tạo đơn là MỘT thao tác nguyên tử.
     *
     * VÌ SAO PHẢI LÀ UNIQUE Ở CƠ SỞ DỮ LIỆU, KHÔNG CHỈ KIỂM TRA BẰNG PHP:
     * Hai request chạy song song đều có thể đọc thấy "chưa có đơn nào"
     * rồi cùng ghi. Chỉ ràng buộc UNIQUE của cơ sở dữ liệu mới thật sự
     * nguyên tử — một bản ghi được nhận, bản còn lại bị từ chối, và mã
     * PHP bắt lỗi đó rồi trả về chính đơn đã tạo.
     *
     * NULLABLE: những đơn có trước migration này không có khoá. MySQL
     * cho phép nhiều giá trị NULL trong một chỉ mục UNIQUE, nên các đơn
     * cũ không xung đột với nhau.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('idempotency_key', 64)
                ->nullable()
                ->after('order_number');

            $table->unique('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Bỏ chỉ mục trước rồi mới bỏ cột — MySQL không cho xoá cột
            // đang nằm trong một chỉ mục.
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
