<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TRẢ HÀNG CHO NHÀ CUNG CẤP — hàng hỏng, giao sai, không đạt.
 * ============================================================
 * VÌ SAO KHÔNG TẠO BẢNG MỚI CHO HÀNG ĐẾM ĐƯỢC.
 *
 * Hệ thống ĐÃ CÓ đúng cơ chế cần thiết, và nó đã được ghi rõ trong
 * ProfitReport: dòng phiếu nhập với SỐ LƯỢNG ÂM được lũy kế vào bảng giá
 * vốn — "phiếu điều chỉnh nhập nhầm 5 cái @48.000 phải kéo giá vốn về
 * đúng như chưa từng nhập nhầm".
 *
 * Trả hàng cho vựa chính là việc đó: bớt N cái khỏi kho VÀ khỏi nền giá
 * vốn, ở đúng đơn giá đã mua. Dựng một bảng riêng rồi dạy lại
 * ProfitReport cách trừ là chép lại một logic đã đúng, và hai bản chép sẽ
 * lệch nhau ở đúng chỗ khó thấy nhất.
 *
 * Nên phiếu trả là một `stock_receipt` với `kind = tra_ncc`, số lượng âm,
 * cộng ba cột riêng cho phần nghiệp vụ mà phiếu nhập không có: vì sao
 * trả, và tiền được xử lý thế nào.
 *
 * ============================================================
 * HOA TƯƠI ĐI ĐƯỜNG KHÁC, vì hoa không có tồn kho để bớt.
 *
 * Lô hoa giữ tiền ở mức LÔ, nên trả hàng là bớt tiền của chính lô đó.
 * Ghi thẳng lên lô: một lô thực tế chỉ bị trả một lần, ngay lúc nhận
 * hàng và phát hiện dập nát.
 *
 * ============================================================
 * CÁCH XỬ LÝ TIỀN QUYẾT ĐỊNH GIÁ VỐN CÓ ĐỔI HAY KHÔNG — và đây là chỗ
 * dễ làm sai nhất:
 *
 *   - Hoàn tiền / trừ công nợ  -> tiền quay về, giá vốn GIẢM.
 *   - Đổi hàng khác            -> vẫn nhận đủ hàng, giá vốn GIỮ NGUYÊN.
 *   - Không được gì            -> cửa hàng chịu, giá vốn GIỮ NGUYÊN.
 *
 * Trừ giá vốn trong hai trường hợp sau là tự tặng cho cửa hàng một khoản
 * lãi không có thật.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->string('return_reason', 32)->nullable()->after('note');
            $table->string('settlement', 32)->nullable()->after('return_reason');

            // Số tiền vựa trả lại hoặc trừ công nợ. NULL khi đổi hàng
            // hoặc không được gì — khác 0, vì 0 là "được trả 0 đồng".
            $table->decimal('settlement_amount', 12, 2)->nullable()->after('settlement');

            // Phiếu nhập gốc mà lô hàng này đến từ đó.
            $table->foreignId('return_of_id')->nullable()->after('settlement_amount')
                ->constrained('stock_receipts')->nullOnDelete();
        });

        Schema::table('flower_lots', function (Blueprint $table) {
            $table->decimal('tra_lai_qty', 12, 2)->nullable()->after('hao_hut');
            $table->decimal('tra_lai_tien', 12, 2)->nullable()->after('tra_lai_qty');
            $table->string('tra_lai_ly_do', 32)->nullable()->after('tra_lai_tien');
            $table->string('tra_lai_settlement', 32)->nullable()->after('tra_lai_ly_do');
            $table->timestamp('tra_lai_at')->nullable()->after('tra_lai_settlement');
        });
    }

    public function down(): void
    {
        Schema::table('flower_lots', function (Blueprint $table) {
            $table->dropColumn([
                'tra_lai_qty', 'tra_lai_tien', 'tra_lai_ly_do',
                'tra_lai_settlement', 'tra_lai_at',
            ]);
        });

        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('return_of_id');
            $table->dropColumn(['return_reason', 'settlement', 'settlement_amount']);
        });
    }
};
