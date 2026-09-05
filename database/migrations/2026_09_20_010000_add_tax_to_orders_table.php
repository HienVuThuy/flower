<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ghi phần thuế của từng đơn hàng.
 * ============================================================
 * VÌ SAO LƯU VÀO ĐƠN CHỨ KHÔNG TÍNH LẠI KHI CẦN.
 *
 * Tính lại từ `grand_total` và thuế suất hiện tại thì mọi đơn cũ sẽ đổi
 * con số ngay khi nhà nước đổi thuế suất — báo cáo quý trước tự nhiên
 * khác đi. Đơn hàng là bản ghi lịch sử: nó phải giữ mức thuế ÁP DỤNG LÚC
 * ĐẶT, y như nó giữ giá bán lúc đặt.
 *
 * Lưu CẢ HAI, và đó không phải dư thừa:
 *
 *   - `tax_rate`   trả lời "vì sao con số này" — kiểm toán cần biết mức
 *                  nào đã được áp, không phải chỉ số tiền.
 *   - `tax_amount` là con số đã CHỐT. Tính lại từ tỉ lệ sẽ ra sai số làm
 *                  tròn khác với lúc ghi đơn, và tổng của một trăm đơn
 *                  sẽ không khớp sổ.
 *
 * ============================================================
 * KHÔNG ĐỔI `grand_total`, VÀ ĐÓ LÀ ĐIỂM MẤU CHỐT.
 *
 * Giá niêm yết đã bao gồm VAT (xem config/tax.php), nên thuế được TÁCH
 * RA từ tổng chứ không cộng thêm vào. Migration này KHÔNG làm bất kỳ
 * khách nào phải trả thêm một đồng, và không con số nào trên giao diện
 * khách hàng thay đổi.
 *
 * Đơn cũ để NULL: hệ thống chưa hề tính thuế lúc chúng được đặt, và điền
 * ngược một con số vào đó là bịa ra dữ liệu kế toán chưa từng tồn tại.
 * NULL đọc ra là "không có số liệu", còn 0 đọc ra là "thuế bằng không" —
 * hai điều khác hẳn nhau khi đối chiếu sổ sách.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            /*
             * decimal(6,5): đủ cho mọi thuế suất từ 0% tới 99,999%.
             * Năm chữ số thập phân vì có nước dùng mức lẻ như 8,25%.
             */
            $table->decimal('tax_rate', 6, 5)->nullable()->after('grand_total');
            $table->decimal('tax_amount', 12, 2)->nullable()->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'tax_amount']);
        });
    }
};
