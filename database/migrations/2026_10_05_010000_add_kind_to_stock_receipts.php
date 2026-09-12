<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TỒN ĐẦU KỲ — hàng đã nằm trên kệ từ trước khi có hệ thống.
 * ============================================================
 * VẤN ĐỀ CÓ THẬT, đo được: 45 mặt hàng đang có tồn > 0 và KHÔNG mặt hàng
 * nào từng có phiếu nhập. Nên trang Lãi gộp báo "0,0% doanh thu có giá
 * vốn" — nó không hỏng, nó đang nói thật, và nó sẽ nói thật như vậy mãi
 * mãi vì không có đường nào để số tồn ban đầu có giá vốn.
 *
 * Luật tính giá vốn hiện hành (xem ProfitReport) là: bình quân gia quyền
 * các lần nhập TỚI NGÀY BÁN, và *dòng bán trước lần nhập đầu tiên thì
 * không có giá vốn*. Đúng — nhưng nó biến toàn bộ hàng có sẵn thành
 * vùng tối vĩnh viễn.
 *
 * ============================================================
 * CÁCH LÀM CHUẨN CỦA KẾ TOÁN KHO: một chứng từ "tồn đầu kỳ".
 *
 * Lập vào ngày bắt đầu dùng hệ thống, khai số đang có và giá vốn ƯỚC
 * TÍNH. Từ mốc đó trở đi mọi thứ có giá vốn.
 *
 * ============================================================
 * KHÁC BIỆT SỐNG CÒN: PHIẾU TỒN ĐẦU KỲ **KHÔNG CỘNG VÀO KHO**.
 *
 * Hàng đã nằm trên kệ rồi. Cộng thêm lần nữa là nhân đôi tồn của cả cửa
 * hàng — và sai lệch đó chỉ lộ ra ở lần kiểm kê đầu tiên, lúc không ai
 * còn nhớ vì sao. Phiếu này chỉ khai GIÁ VỐN, không đụng tới SỐ LƯỢNG.
 *
 * ============================================================
 * VÀ NÓ PHẢI TỰ NHẬN LÀ ƯỚC TÍNH.
 *
 * Con số này do người ta nhớ lại chứ không có hoá đơn. Trộn nó vào cùng
 * một rổ với giá vốn có chứng từ rồi gọi chung là "lãi gộp" là làm mất
 * đúng cái đáng tin của báo cáo. `kind` cho phép mọi nơi phân biệt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->string('kind', 24)->default('nhap_moi')->after('code');
            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
    }
};
