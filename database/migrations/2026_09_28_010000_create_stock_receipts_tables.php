<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHIẾU NHẬP KHO.
 * ============================================================
 * VÌ SAO KHÔNG SỬA THẲNG `products.stock_quantity`:
 *
 * Ô nhập số ở trang sản phẩm ghi đè con số cũ, và sau đó không ai trả
 * lời được ba câu hỏi:
 *
 *   1. VÌ SAO TỒN LÀ 47? Không có gì ghi lại đã cộng vào bao nhiêu, lúc
 *      nào, do ai. Kho lệch so với thực tế thì không có đường lần ngược.
 *   2. MUA VÀO BAO NHIÊU TIỀN? Cả hệ thống không có một chỗ nào lưu giá
 *      vốn — nên không tính được lãi/lỗ (xem QĐ-210).
 *   3. AI GHI ĐÈ CỦA AI? Hai người cùng mở trang sản phẩm, cùng sửa tồn:
 *      người bấm Lưu sau ghi đè hoàn toàn người trước. Không lỗi, không
 *      cảnh báo, và số hàng vừa nhập của người kia biến mất.
 *
 * Phiếu nhập giải quyết cả ba: nó là một bản ghi CỘNG THÊM có ngày, có
 * người, có giá — không phải một phép gán đè.
 *
 * ============================================================
 * `stock_quantity` VẪN LÀ NGUỒN SỰ THẬT cho việc bán hàng.
 *
 * Không tính tồn bằng cách cộng dồn phiếu nhập trừ đi đơn đã bán: phép
 * đó chạy qua toàn bộ lịch sử mỗi lần có người xem một sản phẩm, và nó
 * sai ngay khi có một lần điều chỉnh thủ công nào đó không đi qua phiếu.
 *
 * Phiếu nhập CỘNG VÀO cột đó, trong cùng một transaction, có khoá hàng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_receipts', function (Blueprint $table) {
            $table->id();

            /*
             * Mã phiếu để người ta gọi tên nhau: "phiếu NK-260928-A3F2".
             * Không dùng id tự tăng làm mã: nó để lộ tổng số phiếu và
             * không đọc được qua điện thoại.
             */
            $table->string('code', 40)->unique();

            $table->string('supplier', 200)->nullable();
            $table->string('note', 500)->nullable();

            /*
             * NGÀY NHẬP THẬT, tách khỏi `created_at`.
             *
             * Hàng về thứ Bảy nhưng thứ Hai mới có người ngồi nhập máy là
             * chuyện bình thường. Gộp hai mốc làm một thì báo cáo nhập
             * hàng theo tháng sai vào đúng những ngày cuối tháng.
             */
            $table->date('received_at');

            /*
             * 'draft'  — đang soạn, CHƯA cộng vào kho
             * 'posted' — đã ghi sổ, kho đã cộng
             *
             * Xem App\Enums\StockReceiptStatus. Hai trạng thái là đủ:
             * phiếu đã ghi sổ thì không sửa, chỉ lập phiếu điều chỉnh.
             */
            $table->string('status', 20)->default('draft');

            // Mốc ghi sổ — cũng là dấu hiệu "đã cộng vào kho rồi".
            $table->timestamp('posted_at')->nullable();

            /*
             * AI LẬP PHIẾU. nullOnDelete: xoá tài khoản không được xoá
             * mất chứng từ kho — cột `created_by_name` giữ lại cái tên.
             */
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_name', 120)->nullable();

            $table->timestamps();

            $table->index(['status', 'received_at']);
        });

        Schema::create('stock_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_receipt_id')->constrained()->cascadeOnDelete();

            /*
             * ĐƠN VỊ KHO LÀ (SẢN PHẨM, QUY CÁCH).
             *
             * "Lưỡi hổ mini" có hai chậu, mỗi chậu một kho riêng. Nhập
             * theo sản phẩm thì không biết cộng vào chậu nào.
             *
             * nullOnDelete: sản phẩm bị xoá thì phiếu cũ vẫn còn — nó là
             * bằng chứng đã chi tiền. `product_name` chụp lại tên.
             */
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            $table->string('product_name', 255);
            $table->string('variant_name', 255)->nullable();

            $table->integer('quantity');

            /*
             * GIÁ VỐN MỘT ĐƠN VỊ — thứ cả hệ thống đang thiếu.
             *
             * nullable vì có lô hàng không biết giá (hàng tặng, hàng mẫu,
             * hàng tự nhân giống). NULL nghĩa là "không có số liệu", KHÁC
             * hẳn 0 ("nhận không mất tiền") — và gộp lại thì giá vốn bình
             * quân bị kéo xuống bởi những lô chưa ai điền giá.
             */
            $table->decimal('unit_cost', 14, 2)->nullable();

            $table->timestamps();

            $table->index(['product_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_receipt_items');
        Schema::dropIfExists('stock_receipts');
    }
};
