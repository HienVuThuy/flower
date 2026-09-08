<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CHỤP THUẾ VÀO TỪNG DÒNG HÀNG, không chỉ vào đơn.
 * ============================================================
 * Đơn đã có `tax_rate` và `tax_amount` (xem 2026_09_20_010000). Vậy vì
 * sao còn cần ở dòng hàng?
 *
 * VÌ TỪ NAY MỖI SẢN PHẨM CÓ THỂ CHỊU MỘT MỨC KHÁC NHAU. Một đơn gồm bó
 * hoa (nông sản) và chậu sứ (hàng hoá thông thường) mang hai thuế suất
 * trong cùng một đơn, và một con số ở đầu đơn không diễn tả nổi. Hoá đơn
 * GTGT cũng phải tách theo từng mức thuế suất — không có số liệu ở dòng
 * hàng thì không dựng được bảng đó.
 *
 * ============================================================
 * BA CỘT, MỖI CỘT TRẢ LỜI MỘT CÂU HỎI KHÁC NHAU:
 *
 *   - `discount_amount` — phần mã giảm giá PHÂN BỔ cho dòng này. Mã
 *     giảm giá áp cho cả đơn, nhưng thuế thì tính trên từng dòng theo
 *     mức riêng của nó, nên phải biết dòng này thực sự còn lại bao nhiêu
 *     tiền sau khi giảm. Không có cột này thì phải chia lại mỗi lần đọc
 *     — và cách chia có thể đã đổi từ lâu.
 *
 *   - `tax_rate` — vì sao con số kia là con số đó. Nhà nước đổi mức, hay
 *     admin đổi phân loại sản phẩm, thì đơn cũ vẫn giữ mức đã áp lúc đặt.
 *
 *   - `tax_amount` — con số đã CHỐT. Tính lại từ tỉ lệ sẽ ra sai số làm
 *     tròn khác với lúc ghi, và tổng của một trăm đơn sẽ lệch.
 *
 * NULL ở hai cột thuế nghĩa là "không có số liệu thuế cho dòng này" —
 * hàng không thuộc diện chịu VAT, hoặc đơn được đặt khi tính thuế đang
 * tắt. KHÁC HẲN với 0 ("chịu thuế suất 0%").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            /*
             * Mặc định 0, KHÔNG nullable: mọi dòng đều có một phần giảm
             * xác định, kể cả khi phần đó bằng không. Ở đây 0 và "không
             * biết" không khác nhau — đơn không dùng mã thì mọi dòng
             * được giảm đúng 0₫.
             */
            $table->decimal('discount_amount', 12, 2)
                ->default(0)
                ->after('line_total');

            $table->decimal('tax_rate', 6, 5)->nullable()->after('discount_amount');
            $table->decimal('tax_amount', 12, 2)->nullable()->after('tax_rate');
        });

        Schema::table('orders', function (Blueprint $table) {
            /*
             * THUẾ CỦA PHÍ VẬN CHUYỂN, TÁCH RIÊNG.
             *
             * Vận chuyển là một DỊCH VỤ, có thuế suất của riêng nó và
             * trên hoá đơn nó là một dòng riêng. Gộp vào `tax_amount`
             * tổng thì không còn tách ra được, và khi ấy:
             *
             *     tổng thuế của đơn  !=  tổng thuế các dòng hàng
             *
             * mà không có gì giải thích phần chênh. Có cột này thì đẳng
             * thức đối soát luôn đúng:
             *
             *     orders.tax_amount = SUM(order_items.tax_amount)
             *                       + orders.shipping_tax_amount
             */
            $table->decimal('shipping_tax_amount', 12, 2)
                ->nullable()
                ->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_tax_amount');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'tax_rate', 'tax_amount']);
        });
    }
};
