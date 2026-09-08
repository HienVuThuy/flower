<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DỮ LIỆU HOÁ ĐƠN — tách khỏi Đơn hàng.
 * ============================================================
 * ĐƠN HÀNG KHÔNG PHẢI HOÁ ĐƠN. Đây là chỗ rất nhiều hệ thống làm gộp và
 * sau đó không gỡ ra được.
 *
 *   Order   — dữ liệu THƯƠNG MẠI của website: khách đặt gì, trả bao
 *             nhiêu, giao tới đâu. Sinh ra ngay khi bấm đặt hàng.
 *
 *   Invoice — chứng từ THUẾ/KẾ TOÁN: bán cho ai (có mã số thuế), tiền
 *             hàng chưa thuế bao nhiêu, thuế bao nhiêu. Chỉ sinh ra khi
 *             khách yêu cầu, và có vòng đời riêng.
 *
 * Ba lý do buộc phải tách:
 *
 *   1. KHÔNG PHẢI ĐƠN NÀO CŨNG CÓ HOÁ ĐƠN. Khách lẻ mua bó hoa thường
 *      không lấy hoá đơn.
 *   2. NGƯỜI MUA TRÊN HOÁ ĐƠN KHÁC NGƯỜI NHẬN HÀNG. Công ty mua hoa
 *      tặng đối tác: hàng giao tới đối tác, hoá đơn đứng tên công ty.
 *   3. ĐƠN SỬA ĐƯỢC, HOÁ ĐƠN THÌ KHÔNG. Sau khi phát hành, hoá đơn chỉ
 *      được điều chỉnh/thay thế bằng chứng từ khác, không sửa đè.
 *
 * ============================================================
 * ⚠️ BẢNG NÀY LƯU DỮ LIỆU HOÁ ĐƠN, KHÔNG PHÁT HÀNH HOÁ ĐƠN ĐIỆN TỬ.
 *
 * Hoá đơn điện tử hợp lệ phải được phát hành theo quy trình và định dạng
 * do quy định về hoá đơn điện tử ấn định, thường qua một nhà cung cấp
 * dịch vụ hoá đơn điện tử. Xuất một tệp PDF từ Laravel KHÔNG làm nên một
 * hoá đơn điện tử hợp pháp.
 *
 * Bảng này là bước CHUẨN BỊ: gom đủ và đúng những trường mà hoá đơn cần,
 * để khi cửa hàng ký hợp đồng với nhà cung cấp thì chỉ việc đẩy dữ liệu
 * sang. Vì vậy `status` mặc định là `draft` và `issued_at` để trống —
 * giao diện phải nói thẳng ra điều đó, không được để khách hiểu nhầm là
 * đã có hoá đơn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            /*
             * MỘT ĐƠN — MỘT HOÁ ĐƠN, ép bằng ràng buộc duy nhất.
             *
             * Không phải vì nghiệp vụ cấm tách hoá đơn, mà vì cửa hàng
             * này chưa có nghiệp vụ giao hàng nhiều đợt. Có ràng buộc
             * thì một lỗi bấm hai lần không tạo ra hai hoá đơn cho cùng
             * một đơn — và đó là loại lỗi phát hiện rất muộn.
             */
            $table->foreignId('order_id')
                ->unique()
                ->constrained()
                /*
                 * Đơn bị xoá cứng thì hoá đơn cũng đi theo. Đơn thật
                 * không bị xoá cứng bao giờ (Order dùng xoá mềm); ràng
                 * buộc này chỉ để không còn hoá đơn mồ côi trong lúc dọn
                 * dữ liệu thử nghiệm.
                 */
                ->cascadeOnDelete();

            /*
             * Số hiệu NỘI BỘ, không phải số hoá đơn do cơ quan thuế quản
             * lý. Khi phát hành qua nhà cung cấp, số thật sẽ do bên đó
             * cấp — và lúc đó cần thêm một cột riêng chứ KHÔNG ghi đè cột
             * này: hai con số phải đối chiếu được với nhau.
             */
            $table->string('invoice_number', 40)->unique();

            /*
             * 'personal' | 'company' — xem App\Enums\InvoiceBuyerType.
             *
             * Quyết định trường nào bắt buộc: cá nhân không có mã số
             * thuế, công ty thì bắt buộc phải có.
             */
            $table->string('buyer_type', 20);

            $table->string('buyer_name', 200);
            $table->string('buyer_tax_code', 20)->nullable();
            $table->string('buyer_address', 300)->nullable();

            /*
             * Nơi nhận hoá đơn — thường KHÁC email đặt hàng: đơn do thư
             * ký đặt, hoá đơn phải về kế toán.
             */
            $table->string('buyer_email', 255)->nullable();

            /*
             * BA CON SỐ, CHỤP LẠI TẠI THỜI ĐIỂM LẬP.
             *
             * Không tính lại từ đơn mỗi lần đọc: đơn còn sửa được (admin
             * đổi phí giao, huỷ một dòng hàng), còn số trên chứng từ thì
             * phải đứng yên. `subtotal` ở đây là tiền hàng CHƯA thuế —
             * khác `orders.subtotal` (đã gồm thuế, vì giá niêm yết đã gồm
             * thuế). Đặt cùng tên mà khác nghĩa là bẫy, nên ghi rõ ở đây
             * và ở App\Models\Invoice.
             */
            $table->decimal('subtotal', 14, 2);
            $table->decimal('tax_total', 14, 2);
            $table->decimal('grand_total', 14, 2);

            /*
             * TÁCH THEO TỪNG MỨC THUẾ SUẤT.
             *
             * Hoá đơn phải ghi riêng từng mức: 5.000.000₫ chịu 8% và
             * 2.000.000₫ chịu 10% là hai dòng khác nhau, không phải một
             * dòng "7.000.000₫ thuế hỗn hợp". Dựng sẵn lúc lập chứ không
             * tính lại lúc in: dòng hàng của đơn có thể đã đổi.
             *
             * Dạng: [{"rate":"0.08000","net":"...","tax":"..."}, ...]
             */
            $table->json('rate_breakdown')->nullable();

            /*
             * 'draft'    — đã gom đủ dữ liệu, CHƯA phát hành
             * 'issued'   — đã phát hành qua nhà cung cấp hoá đơn điện tử
             * 'cancelled'— đã huỷ
             *
             * Xem App\Enums\InvoiceStatus. Hiện tại hệ thống chỉ tạo ra
             * `draft`: chưa tích hợp nhà cung cấp nào, và KHÔNG được để
             * giao diện hiện trạng thái mà backend không đạt tới được.
             */
            $table->string('status', 20)->default('draft');
            $table->timestamp('issued_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
