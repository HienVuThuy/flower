<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cước GHN: ai trả, và "chưa có số liệu" khác 0₫.
 * ============================================================
 * HAI THAY ĐỔI, cùng phục vụ một câu hỏi: tháng này cửa hàng bù bao
 * nhiêu tiền ship. Thiếu một trong hai thì câu trả lời là bịa.
 *
 * ------------------------------------------------------------
 * 1. `ghn_fee_payer` — AI TRẢ CƯỚC CHO VẬN ĐƠN NÀY.
 *
 * Mọi vận đơn tạo trước migration này đều gửi GHN `payment_type_id = 2`
 * (người nhận trả) — giá trị viết cứng từ ngày có tính năng, và API chi
 * tiết vận đơn của GHN xác nhận lại đúng như vậy. Với những đơn đó cửa
 * hàng không trả GHN đồng nào, nên chúng không được tính vào khoản bù.
 *
 * Vì thế ĐIỀN 'buyer' cho mọi đơn ĐÃ CÓ mã vận đơn. Đơn chưa có vận đơn
 * để NULL: chưa gửi thì chưa có ai trả gì cả.
 *
 * ------------------------------------------------------------
 * 2. `ghn_total_fee` THÀNH NULLABLE, và 0 cũ đổi thành NULL.
 *
 * Cột cũ `integer default 0` dùng số 0 cho "không hỏi được GHN" (GHN
 * sập, địa chỉ thiếu mã, đơn đặt trước khi nối GHN). Nhưng 0 là một lời
 * khẳng định — "cước bằng không" — và cộng vào báo cáo thì mỗi đơn như
 * vậy thành một đơn cửa hàng LÃI trọn phí ship của khách.
 *
 * GHN không bao giờ tính cước 0₫ cho một kiện hàng thật, nên mọi số 0
 * đang có đều mang nghĩa "không biết". Đổi sang NULL không làm mất
 * thông tin nào.
 *
 * ROLLBACK: NULL quay về 0 và cột quay về NOT NULL DEFAULT 0 — đúng trạng
 * thái cũ, cũng không mất gì (cột cũ vốn không phân biệt được hai nghĩa).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('ghn_fee_payer', 10)->nullable()->after('ghn_total_fee');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->integer('ghn_total_fee')->nullable()->default(null)->change();
        });

        DB::table('orders')->where('ghn_total_fee', 0)->update(['ghn_total_fee' => null]);

        DB::table('orders')
            ->whereNotNull('ghn_order_code')
            ->update(['ghn_fee_payer' => 'buyer']);
    }

    public function down(): void
    {
        DB::table('orders')->whereNull('ghn_total_fee')->update(['ghn_total_fee' => 0]);

        Schema::table('orders', function (Blueprint $table) {
            $table->integer('ghn_total_fee')->nullable(false)->default(0)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('ghn_fee_payer');
        });
    }
};
