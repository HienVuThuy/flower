<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Làm cho chỉ mục chống trùng dòng giỏ hàng THẬT SỰ chống được trùng.
 * ============================================================
 * VẤN ĐỀ: bảng đã có unique(cart_id, product_id, product_variant_id) từ
 * đầu, và nhìn tên thì tưởng xong việc. Nhưng trong SQL, NULL không bằng
 * bất cứ thứ gì — kể cả một NULL khác. Nên với hàng KHÔNG CÓ QUY CÁCH
 * (product_variant_id = NULL, tức phần lớn sản phẩm của cửa hàng), hai
 * dòng y hệt nhau vẫn được coi là hai dòng khác nhau và chèn được cả hai.
 *
 * Đã kiểm chứng trên chính cơ sở dữ liệu này: chèn hai dòng giống hệt
 * nhau vào một giỏ, cả hai đều vào được, đếm ra 2.
 *
 * VÌ SAO CÓ HẠI: CartService đã tìm dòng cũ trước khi thêm, nên đường
 * thường ngày không sinh ra trùng. Nhưng "tìm rồi thêm" là hai bước:
 * bấm nhanh hai lần vào "Thêm vào giỏ" — nay là nút AJAX nên bấm liên
 * tiếp còn dễ hơn trước — thì hai request cùng thấy "chưa có dòng nào"
 * và cùng chèn. Khách thấy một sản phẩm nằm hai dòng trong giỏ, mỗi
 * dòng một số lượng riêng. Chỉ mục sinh ra đúng để chặn cảnh đó.
 *
 * CÁCH SỬA: thêm một cột SINH TỰ ĐỘNG quy NULL về số 0, rồi đặt chỉ mục
 * lên cột đó. Cột này do cơ sở dữ liệu tự tính, mã nguồn không bao giờ
 * ghi vào — không có gì phải nhớ, không có chỗ nào quên.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * DỌN TRÙNG TRƯỚC KHI ĐẶT CHỈ MỤC.
         *
         * Nếu dữ liệu đang chạy đã có dòng trùng (do đúng lỗi này), câu
         * lệnh tạo chỉ mục sẽ ngã và cả migration hỏng giữa chừng. Gộp
         * số lượng về dòng cũ nhất rồi xoá phần còn lại — cộng dồn chứ
         * KHÔNG vứt bớt, vì mỗi dòng đó là hàng khách thật sự đã chọn.
         */
        $trung = DB::table('cart_items')
            ->selectRaw('cart_id, product_id, COALESCE(product_variant_id, 0) as vkey,'
                .' MIN(id) as giu_lai, SUM(quantity) as tong, COUNT(*) as so_dong')
            ->groupBy('cart_id', 'product_id', 'vkey')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($trung as $nhom) {
            DB::table('cart_items')->where('id', $nhom->giu_lai)
                ->update(['quantity' => (int) $nhom->tong]);

            DB::table('cart_items')
                ->where('cart_id', $nhom->cart_id)
                ->where('product_id', $nhom->product_id)
                ->whereRaw('COALESCE(product_variant_id, 0) = ?', [$nhom->vkey])
                ->where('id', '!=', $nhom->giu_lai)
                ->delete();
        }

        /*
         * CHỖ NÀY PHẢI ĐI ĐÚNG THỨ TỰ.
         *
         * Khoá ngoại cart_id đang MƯỢN chỉ mục cũ để tra cứu — nó là cột
         * đầu tiên của chỉ mục đó. Xoá chỉ mục trước thì cơ sở dữ liệu
         * từ chối thẳng: "Cannot drop index: needed in a foreign key
         * constraint". Nên dựng một chỗ dựa tạm cho khoá ngoại trước,
         * xong việc mới rút đi.
         */
        Schema::table('cart_items', function (Blueprint $table) {
            $table->index('cart_id', 'cart_items_cart_id_tam');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_unique_line');
        });

        /*
         * storedAs chứ không phải virtualAs: chỉ mục duy nhất trên cột
         * ảo tuỳ phiên bản cơ sở dữ liệu mà có hoặc không hỗ trợ. Cột
         * lưu thật thì chắc chắn đặt chỉ mục được, đổi lại tốn vài byte
         * mỗi dòng — cái giá không đáng bàn cho một bảng giỏ hàng.
         */
        Schema::table('cart_items', function (Blueprint $table) {
            $table->unsignedBigInteger('variant_key')
                ->storedAs('COALESCE(product_variant_id, 0)')
                ->after('product_variant_id');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->unique(['cart_id', 'product_id', 'variant_key'], 'cart_items_unique_line');
        });

        // Chỉ mục mới cũng bắt đầu bằng cart_id nên khoá ngoại lại có
        // chỗ dựa — rút chỗ dựa tạm đi.
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex('cart_items_cart_id_tam');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->index('cart_id', 'cart_items_cart_id_tam');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_unique_line');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('variant_key');
        });

        // Trả lại đúng chỉ mục cũ — kể cả khi nó không chặn được NULL.
        // Lùi migration là phải về đúng hình dạng trước đó, không phải
        // về một hình dạng "tốt hơn" mà bản cũ không biết tới.
        Schema::table('cart_items', function (Blueprint $table) {
            $table->unique(['cart_id', 'product_id', 'product_variant_id'], 'cart_items_unique_line');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex('cart_items_cart_id_tam');
        });
    }
};
