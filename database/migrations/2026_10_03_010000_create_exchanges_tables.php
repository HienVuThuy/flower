<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ĐỔI HÀNG — khách trả lại món này, nhận món khác.
 * ============================================================
 * VÌ SAO LÀ MỘT CHỨNG TỪ RIÊNG, không phải "sửa lại đơn cũ".
 *
 * Đơn hàng là bản chụp của một lần mua đã xảy ra, và đã xuất hoá đơn.
 * Sửa dòng hàng trên đơn cũ là viết lại lịch sử: hoá đơn đã gửi cho
 * khách nói một đằng, cơ sở dữ liệu nói một nẻo, và không còn cách nào
 * biết cửa hàng đã thật sự giao gì.
 *
 * Phiếu đổi hàng ghi lại ĐÚNG những gì xảy ra thêm: món nào quay về, món
 * nào đi ra, chênh lệch bao nhiêu, ai lập, lúc nào.
 *
 * ============================================================
 * HAI CHIỀU TRONG MỘT BẢNG DÒNG.
 *
 * `chieu = tra_ve` trỏ tới dòng hàng CỦA ĐƠN CŨ (order_item_id) — nhờ
 * vậy giá lấy đúng giá khách ĐÃ TRẢ, kể cả khi giá niêm yết hôm nay đã
 * khác. `chieu = gui_di` trỏ tới sản phẩm, và chụp lại giá tại thời điểm
 * đổi.
 *
 * Tách hai bảng thì mọi truy vấn "phiếu này có những gì" phải hợp nhất
 * hai nguồn, và thứ tự dòng không còn ý nghĩa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchanges', function (Blueprint $table) {
            $table->id();

            $table->string('code', 32)->unique();

            /*
             * KHÔNG cascade khi xoá đơn.
             *
             * Đơn hàng dùng xoá mềm, nên "xoá" không thật sự xoá dòng.
             * Nhưng nếu có ngày ai đó xoá cứng, phiếu đổi hàng KHÔNG được
             * biến mất theo — nó là chứng từ có tiền và có hàng đi kèm.
             */
            $table->foreignId('order_id')->constrained()->restrictOnDelete();

            $table->string('reason', 32);
            $table->string('status', 32)->default('cho_nhan');
            $table->text('note')->nullable();

            /*
             * BA CON SỐ TIỀN, LƯU BẢN CHỤP chứ không tính lại khi đọc.
             *
             * Giá hàng đổi thay đổi theo thời gian và theo khuyến mại.
             * Tính lại lúc mở phiếu ra xem thì một phiếu lập tháng trước
             * sẽ tự đổi số — và không ai biết con số nào là con số đã
             * thoả thuận với khách.
             */
            $table->decimal('tien_hang_tra', 12, 2)->default(0);
            $table->decimal('tien_hang_moi', 12, 2)->default(0);
            $table->decimal('phi_ship', 12, 2)->default(0);

            // Dương: khách bù thêm. Âm: cửa hàng trả lại. 0: đổi ngang.
            $table->decimal('chenh_lech', 12, 2)->default(0);

            // Phần khách đã bù thật sự (thu khi giao hàng mới).
            $table->decimal('da_thu', 12, 2)->default(0);

            /*
             * Khi cửa hàng nợ lại tiền, phiếu này KHÔNG tự trả — nó tạo
             * một chứng từ hoàn tiền và trỏ sang đó. Tiền chỉ có một
             * đường ra khỏi hệ thống, và đường đó đã có sổ riêng.
             */
            $table->foreignId('refund_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('nhan_hang_at')->nullable();
            $table->timestamp('hoan_tat_at')->nullable();
            $table->timestamp('huy_at')->nullable();
            $table->string('ly_do_huy', 255)->nullable();

            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('exchange_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('exchange_id')->constrained()->cascadeOnDelete();

            // 'tra_ve' hoặc 'gui_di'.
            $table->string('chieu', 16);

            // Chỉ chiều trả về mới có: trỏ tới dòng của đơn cũ.
            $table->foreignId('order_item_id')->nullable()->constrained()->restrictOnDelete();

            // Chỉ chiều gửi đi mới có.
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();

            /*
             * TÊN HÀNG LƯU BẢN CHỤP.
             *
             * Sản phẩm đổi tên, hoặc bị xoá mềm, thì phiếu cũ vẫn phải
             * đọc được là đã đổi món gì. Cùng nguyên tắc với order_items.
             */
            $table->string('ten_hang', 255);

            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);

            // Hàng trả về có bán lại được không — chỉ có nghĩa ở chiều trả về.
            $table->boolean('restock')->default(false);

            $table->timestamps();

            $table->index(['exchange_id', 'chieu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_items');
        Schema::dropIfExists('exchanges');
    }
};
