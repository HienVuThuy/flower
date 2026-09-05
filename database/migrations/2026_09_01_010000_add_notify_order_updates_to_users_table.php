<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Khách tự chọn có nhận thư báo đổi trạng thái đơn hay không.
     * ============================================================
     * PHẠM VI HẸP CÓ CHỦ ĐÍCH — chỉ một cột, chỉ một loại thư.
     *
     * Không làm bảng "tuỳ chọn thông báo" nhiều dòng nhiều kênh, vì hệ
     * thống hiện chỉ có MỘT kênh (email) và MỘT loại thư có thể tắt.
     * Dựng sẵn bảng cho những kênh chưa tồn tại là đoán mò hình dạng của
     * thứ chưa ai dùng. Khi nào có SMS hay thông báo đẩy thì tách bảng,
     * lúc đó mới biết nó thật sự cần những cột gì.
     *
     * VÌ SAO CHỈ TẮT ĐƯỢC THƯ ĐỔI TRẠNG THÁI:
     *   - Thư XÁC NHẬN ĐƠN là biên nhận mua hàng — bằng chứng giao dịch,
     *     không phải thông báo, nên không cho tắt.
     *   - Thư BẢO MẬT (đổi mật khẩu, đặt lại mật khẩu) tồn tại đúng cho
     *     lúc tài khoản bị chiếm; cho tắt là mở sẵn cửa cho kẻ tấn công
     *     tắt hộ nạn nhân.
     *   - Còn lại là thư "đơn đã xác nhận / đang giao / đã giao" — thứ
     *     duy nhất mà tắt đi không mất gì ngoài sự tiện lợi.
     *
     * MẶC ĐỊNH true: khách chưa bày tỏ ý gì thì mặc định là muốn biết
     * đơn của mình đi tới đâu.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_order_updates')
                ->default(true)
                ->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notify_order_updates');
        });
    }
};
