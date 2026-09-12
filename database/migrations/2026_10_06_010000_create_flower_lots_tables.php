<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HOA TƯƠI KHÔNG ĐẾM THEO CÁI — đếm theo LÔ.
 * ============================================================
 * VÌ SAO HOA KHÔNG DÙNG ĐƯỢC CÁCH TÍNH CỦA HÀNG THƯỜNG.
 *
 * Chậu, đất, kéo cắt cành: mua một cái, bán một cái, đếm được. Hoa tươi
 * thì không, vì bốn lý do cùng lúc:
 *
 *   1. ĐƠN VỊ MUA KHÁC ĐƠN VỊ BÁN. Mua theo bó buôn hoặc theo cân ở
 *      chợ; bán theo bó đặt riêng mà mỗi khách một kiểu. Không có tỉ lệ
 *      cố định nào giữa hai đầu.
 *   2. SỐ LƯỢNG QUÁ LỚN ĐỂ ĐẾM. Một lần lấy hàng là hàng trăm cành.
 *      Đếm từng cành mỗi ngày là một cuộc chiến không thắng được, và số
 *      đếm sai còn tệ hơn không đếm.
 *   3. HAO HỤT LÀ BÌNH THƯỜNG VÀ LỚN. Hoa héo, gãy, nở quá. Hệ thống
 *      đếm từng cái sẽ báo "thiếu hàng" mỗi ngày, và người dùng học cách
 *      lờ cảnh báo đi — rồi lờ luôn cả cảnh báo thật.
 *   4. MỖI LOẠI MỘT ĐẶC THÙ. Hồng giữ được lâu hơn ly; cúc rẻ nhưng
 *      phải lấy nhiều.
 *
 * ============================================================
 * MÔ HÌNH: MỘT LẦN LẤY HÀNG = MỘT LÔ.
 *
 * Lô ghi: hoa gì, của ai, ngày nào, bao nhiêu (theo đơn vị mua), hết bao
 * nhiêu tiền. Dùng hết thì ĐÓNG LÔ, và lúc đó mới ghi hao hụt — một lần,
 * bằng ước lượng, thay vì đếm mỗi ngày.
 *
 * GIÁ VỐN HOA CỦA MỘT KỲ = TỔNG TIỀN CÁC LÔ ĐÃ ĐÓNG TRONG KỲ. Đây là
 * con số ở MỨC KỲ, không phải mức từng đơn hàng — và đó là sự thật, chứ
 * không phải thiếu sót: không ai biết bó hoa bán hôm qua dùng cành của
 * lô nào.
 *
 * ============================================================
 * VÌ SAO LOẠI HOA LÀ MỘT BẢNG RIÊNG, không phải ô chữ tự do.
 *
 * Cùng lý do với nhà cung cấp: "Hồng đỏ", "hong do" và "Hoa hồng đỏ" là
 * ba loại khác nhau với máy, và câu "loại này mua ở đâu rẻ hơn" — cả lý
 * do người ta chịu ghi chép — không trả lời được.
 *
 * Và nó KHÔNG dùng `plant_taxa`: cây đó là phân loại SINH HỌC (Thực vật
 * → Hạt kín → Hoa hồng). Đây là trục THU MUA — thứ người ta gọi tên khi
 * ra chợ. Trộn hai trục là lỗi đã được cảnh báo ở QĐ-08.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flower_kinds', function (Blueprint $table) {
            $table->id();

            // Không trùng — đây là cả lý do bảng này tồn tại.
            $table->string('name', 120)->unique();

            /*
             * ĐƠN VỊ MẶC ĐỊNH khi lấy loại hoa này.
             *
             * Chỉ là gợi ý điền sẵn; đơn vị thật nằm trên từng lô, vì có
             * hôm mua theo bó có hôm mua theo cân.
             */
            $table->string('default_unit', 16)->default('bo');

            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        Schema::create('flower_lots', function (Blueprint $table) {
            $table->id();

            $table->string('code', 32)->unique();

            $table->foreignId('flower_kind_id')->constrained()->restrictOnDelete();

            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();

            // Bản chụp tên, cùng lý do với phiếu nhập: nhà cung cấp đổi
            // tên thì lô cũ vẫn phải đọc được là hồi đó mua của ai.
            $table->string('supplier_name', 160)->nullable();

            $table->date('purchased_at');

            /*
             * SỐ LƯỢNG CÓ PHẦN THẬP PHÂN.
             *
             * Mua theo cân thì 3,5kg là chuyện thường. Ép số nguyên là
             * ép người dùng làm tròn, và cái làm tròn đó đi thẳng vào
             * giá vốn.
             */
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 16);

            $table->decimal('total_cost', 12, 2);

            $table->string('status', 16)->default('dang_dung');
            $table->timestamp('closed_at')->nullable();

            /*
             * HAO HỤT ghi MỘT LẦN lúc đóng lô, theo cùng đơn vị.
             *
             * Nó KHÔNG làm giảm giá vốn — tiền đã trả rồi. Nó là thước đo
             * CHẤT LƯỢNG: cùng một giá, vựa hao 5% và vựa hao 20% không
             * phải hai lựa chọn ngang nhau.
             */
            $table->decimal('hao_hut', 12, 2)->default(0);

            // Đánh giá chất lượng lô, để so cùng với giá.
            $table->string('quality', 16)->nullable();

            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'purchased_at']);
            $table->index(['flower_kind_id', 'purchased_at']);
            $table->index('closed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flower_lots');
        Schema::dropIfExists('flower_kinds');
    }
};
