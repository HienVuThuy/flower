<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nối đơn hàng với Giao Hàng Nhanh.
 * ============================================================
 * KHÔNG TẠO LẠI BẢNG `orders` VÀ `order_items`.
 *
 * Tài liệu hướng dẫn viết migration tạo mới hai bảng đó, vì nó giả định
 * một dự án chưa có gì. Dự án này đã có cả hai, với 39 đơn thật và đầy
 * đủ nghiệp vụ (mã đơn, trạng thái, mã giảm giá, chụp giá, dòng thời
 * gian). Chạy lại `create` là mất sạch — nên ở đây chỉ THÊM đúng những
 * cột GHN cần.
 *
 * VÌ SAO GIỮ CẢ TÊN CHỮ LẪN MÃ SỐ:
 *
 *   shipping_province / _district / _ward  (chữ)  — đã có
 *   to_district_id / to_ward_code          (mã GHN) — thêm mới
 *
 * Mã số là thứ GHN hiểu; tên chữ là thứ CON NGƯỜI đọc. Đơn hàng là
 * chứng từ phải đọc được sau nhiều năm, kể cả khi GHN đổi mã hoặc cửa
 * hàng đổi sang đơn vị vận chuyển khác. Bỏ tên chữ đi thì nhân viên mở
 * đơn cũ chỉ thấy `to_district_id = 1482` và không biết đó là đâu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            /*
             * Mã vận đơn GHN trả về sau khi tạo đơn thành công.
             *
             * nullable vì đơn chỉ có mã sau khi cửa hàng bàn giao cho
             * GHN — không phải ngay lúc khách đặt. Có index vì đây là
             * đường tra ngược khi GHN báo trạng thái về.
             */
            $table->string('ghn_order_code')->nullable()->index()->after('shipping_fee');

            /*
             * Phí GHN báo, đơn vị VNĐ, KIỂU NGUYÊN.
             *
             * Khác `shipping_fee` (decimal) là con số cửa hàng THU của
             * khách. Hai con số này có thể lệch nhau — cửa hàng miễn phí
             * giao cho đơn lớn nhưng vẫn phải trả GHN đủ. Gộp làm một
             * thì mất luôn khả năng đối soát: không biết tháng này bù lỗ
             * bao nhiêu tiền ship.
             */
            $table->integer('ghn_total_fee')->default(0)->after('ghn_order_code');

            // Mã quận/huyện và phường/xã của GHN cho địa chỉ người nhận.
            $table->integer('to_district_id')->nullable()->after('ghn_total_fee');
            $table->string('to_ward_code', 20)->nullable()->after('to_district_id');

            /*
             * Trạng thái bên VẬN CHUYỂN, tách khỏi `status` của đơn.
             *
             * `status` là việc của cửa hàng (chờ xác nhận → đã xác nhận →
             * đang chuẩn bị…), do người của cửa hàng bấm. Cột này là
             * việc của GHN (ready_to_pick, picking, delivering,
             * delivered, cancel), do GHN báo về.
             *
             * Gộp hai thứ vào một cột thì máy trạng thái của đơn phải
             * hiểu cả những giá trị mà không ai trong cửa hàng đặt ra
             * được, và mỗi lần GHN thêm một trạng thái mới là một lần
             * phải sửa luật nội bộ.
             */
            $table->string('shipping_status', 30)->default('not_shipped')->after('to_ward_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['ghn_order_code']);
            $table->dropColumn([
                'ghn_order_code',
                'ghn_total_fee',
                'to_district_id',
                'to_ward_code',
                'shipping_status',
            ]);
        });
    }
};
