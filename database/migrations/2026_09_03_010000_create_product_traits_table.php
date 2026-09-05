<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nhãn phân loại nhiều-giá-trị của sản phẩm.
     * ============================================================
     * NGHIỆP VỤ CỤ THỂ, không phải bảng "cho đủ bộ":
     *
     *   1. "Cây nào hợp ban công / phòng tắm / bàn làm việc?"
     *      Câu hỏi phổ biến nhất của người mua cây lần đầu. Hiện không
     *      trả lời được: thông tin vị trí nằm trong care_info['position']
     *      dưới dạng chữ tự do, mỗi sản phẩm viết một kiểu.
     *
     *   2. "Cây này hợp mệnh gì?"
     *      Nhu cầu có thật của thị trường cây cảnh Việt Nam. Admin tự gán
     *      — hệ thống KHÔNG suy ra từ tên hay màu cây, vì suy là bịa.
     *
     *   3. "Mua chậu này thì cần thêm gì?"
     *      Để gợi ý mua kèm mà không bắt admin nối tay từng cặp sản phẩm:
     *      phụ kiện mang nhãn `accessory_for = pot` là hợp với mọi cây
     *      chậu, kể cả cây nhập về sau này.
     *
     * VÌ SAO KHÔNG THÊM 3 CỘT JSON VÀO `products`:
     * MariaDB 10.4 không đánh chỉ mục được vào trong JSON, nên mọi câu
     * "tìm sản phẩm có nhãn X" đều phải quét toàn bảng và gọi hàm JSON
     * lên từng dòng. Bảng riêng có chỉ mục ghép (trait_type, trait_value)
     * trả lời đúng câu hỏi đó bằng một lần tra chỉ mục.
     */
    public function up(): void
    {
        Schema::create('product_traits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // varchar chứ không phải enum của MySQL: thêm một giá trị mới
            // vào enum PHP là chuyện của mã nguồn, không nên kéo theo một
            // migration ALTER TABLE khoá bảng.
            $table->string('trait_type', 32);
            $table->string('trait_value', 32);

            $table->timestamps();

            /*
             * UNIQUE — một sản phẩm không thể mang cùng một nhãn hai lần.
             *
             * Form admin gửi mảng checkbox; trình duyệt hay một request
             * dựng tay đều có thể gửi trùng giá trị. Lọc bằng PHP thì
             * đúng cho tới khi có đường ghi thứ hai quên lọc.
             */
            $table->unique(['product_id', 'trait_type', 'trait_value'], 'product_traits_unique');

            /*
             * Chỉ mục ghép cho câu hỏi chính: "sản phẩm nào mang nhãn X".
             * trait_type trước vì nó luôn được so bằng và có ít giá trị
             * hơn — cột chọn lọc kém hơn đứng trước cột chọn lọc tốt hơn
             * ở đây là đúng, vì cả hai đều so bằng và luôn đi cùng nhau.
             */
            $table->index(['trait_type', 'trait_value'], 'product_traits_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_traits');
    }
};
