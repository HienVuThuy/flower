<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NƠI CỬA HÀNG LẤY HÀNG — thành một bảng, không còn là ô chữ tự do.
 * ============================================================
 * TRƯỚC BẢN NÀY: `stock_receipts.supplier` là một `string` gõ tay.
 *
 * Nghe thì tiện, nhưng nó làm mất đúng thứ đáng giá nhất của việc ghi
 * chép thu mua: **so sánh**. Gõ "Vựa Hoa Tươi", "vựa hoa tươi" và "Vua
 * hoa tuoi" là ba nhà cung cấp khác nhau với máy, nên câu hỏi "cùng loại
 * hồng này, mua ở đâu rẻ hơn" không trả lời được — và đó chính là câu
 * hỏi khiến người ta chịu khó ghi chép ngay từ đầu.
 *
 * ============================================================
 * PHÂN LOẠI NGUỒN HÀNG, vì mỗi nguồn một kiểu làm ăn.
 *
 * Nông dân bán rẻ nhưng không đều và phải đặt trước; vựa đắt hơn nhưng
 * lúc nào cũng có; chợ đầu mối rẻ nhất lúc sáng sớm nhưng phải tự đi
 * lấy. Cùng một giá tiền, ba nguồn đó không cùng một lựa chọn — nên
 * `kind` không phải để cho đẹp, nó là thứ để lọc khi cần quyết định.
 *
 * "Vườn nhà" cũng là một nguồn: cây tự trồng không mất tiền mua nhưng
 * KHÔNG PHẢI không có giá vốn (công, đất, giống, phân). Có nó trong danh
 * sách thì phần tự cung cũng ghi sổ được như mọi nguồn khác.
 *
 * ============================================================
 * KHÔNG XOÁ CỨNG. Phiếu nhập cũ trỏ tới đây; xoá đi là mất dấu vết của
 * những lần đã mua. Ngừng làm ăn thì tắt `is_active` — không hiện ở ô
 * chọn nữa, nhưng lịch sử vẫn đọc được.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            $table->string('name', 160);

            /*
             * TÊN KHÔNG TRÙNG — đây là cả lý do bảng này tồn tại.
             *
             * Cho phép trùng thì lại quay về đúng mớ hỗn độn của ô chữ tự
             * do, chỉ khác là lần này có id.
             */
            $table->unique('name');

            $table->string('kind', 32)->default('khac');

            $table->string('phone', 30)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('address', 255)->nullable();

            /*
             * Ghi chú là chỗ đựng thứ không lên được thành cột: "hoa đẹp
             * nhưng hay thiếu hàng cuối tuần", "phải gọi trước 2 hôm",
             * "chỉ nhận tiền mặt". Đây thường là thông tin quyết định
             * việc chọn ai, mà không bảng nào mô hình hoá nổi.
             */
            $table->text('note')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['is_active', 'kind']);
        });

        Schema::table('stock_receipts', function (Blueprint $table) {
            /*
             * GIỮ LẠI cột `supplier` cũ làm BẢN CHỤP TÊN.
             *
             * Cùng nguyên tắc với `order_items.product_name`: nhà cung
             * cấp đổi tên, hay bị tắt đi, thì phiếu cũ vẫn phải đọc được
             * là hồi đó mua của ai. `supplier_id` để nối và so sánh;
             * `supplier` để đọc lại lịch sử.
             */
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('code')
                ->constrained()
                ->nullOnDelete();

            $table->index('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });

        Schema::dropIfExists('suppliers');
    }
};
