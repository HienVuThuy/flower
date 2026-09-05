<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CHẤM ĐIỂM RỦI RO cho đơn hàng.
     * ============================================================
     * VẤN ĐỀ CÓ THẬT VỚI COD: khách đặt xong không nhận. Với hàng công
     * nghiệp thì shipper trả về kho là xong; với hoa tươi thì bó hoa đã
     * cắt, đã bó, đã đi đường — không bán lại được cho ai. Mỗi đơn bùng
     * là mất trắng toàn bộ giá vốn.
     *
     * CHẤM ĐIỂM ĐỂ CON NGƯỜI XEM, KHÔNG ĐỂ MÁY CHẶN.
     *
     * Đây là quyết định quan trọng nhất của tính năng này. Hệ thống chỉ
     * gắn cờ và xếp đơn nghi ngờ lên đầu danh sách; QUYẾT ĐỊNH GỌI ĐIỆN
     * XÁC NHẬN HAY TỪ CHỐI LÀ CỦA NGƯỜI. Tự động chặn đơn dựa trên vài
     * dấu hiệu thống kê sẽ đuổi nhầm khách thật — và khách bị từ chối oan
     * thì không quay lại, còn cửa hàng thì không bao giờ biết mình vừa
     * mất ai.
     *
     * HAI CỘT:
     *   risk_score - 0..100, càng cao càng đáng gọi xác nhận trước khi làm
     *   risk_flags - JSON liệt kê TỪNG dấu hiệu đã cộng điểm
     *
     * risk_flags là phần bắt buộc, không phải trang trí: một con số 60
     * trần trụi thì nhân viên không biết phải kiểm tra gì. Ghi rõ "đơn
     * COD giá trị cao" và "số điện thoại này từng huỷ 3 đơn" thì họ biết
     * cần hỏi gì khi gọi.
     *
     * CHỤP TẠI THỜI ĐIỂM ĐẶT, không tính lại khi xem: điểm phản ánh
     * những gì hệ thống biết LÚC ĐÓ. Tính lại sau ba tháng sẽ cho ra con
     * số khác, và không ai còn đối chiếu được với quyết định đã làm.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('risk_score')->default(0)->after('payment_status');
            $table->json('risk_flags')->nullable()->after('risk_score');

            /*
             * Chỉ mục để trang quản trị lọc "đơn cần xem lại".
             *
             * Phần lớn đơn có điểm 0, nên chỉ mục này rất chọn lọc — đúng
             * kiểu chỉ mục có ích: tìm thiểu số trong một bảng lớn.
             */
            $table->index('risk_score', 'orders_risk_score_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_risk_score_index');
            $table->dropColumn(['risk_score', 'risk_flags']);
        });
    }
};
