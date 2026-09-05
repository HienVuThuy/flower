<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gắn mã giảm giá vào CHƯƠNG TRÌNH, và giới hạn theo hình thức thanh toán.
     * ============================================================
     * 1. `promotion_id` — MÃ CỦA MỘT SỰ KIỆN
     *
     * Trang sự kiện (ví dụ "Giáng sinh an lành") cần chỗ phát mã riêng
     * cho sự kiện đó. Không có cột này thì mọi mã công khai đều hiện ở
     * mọi nơi, và khách vào trang Giáng sinh vẫn thấy mã Tết.
     *
     * nullable: phần lớn mã KHÔNG thuộc sự kiện nào (mã chào bạn mới, mã
     * sinh nhật). Chúng vẫn hiện ở trang Voucher như trước.
     *
     * nullOnDelete chứ không cascade: xoá một chương trình khuyến mại
     * KHÔNG được xoá theo mã giảm giá — mã có thể đã nằm trong ví của
     * khách, và có mã đã được dùng cho đơn hàng thật.
     *
     * 2. `payment_methods` — GIỚI HẠN HÌNH THỨC THANH TOÁN
     *
     * Nhu cầu có thật: cửa hàng muốn khuyến khích chuyển khoản trước
     * (tiền về ngay, không lo bùng hàng) nên phát mã chỉ dùng được khi
     * chuyển khoản. Không có cột này thì mã đó không thực hiện được.
     *
     * NULL = mọi hình thức. Đó là hành vi cũ, nên mã đang có không đổi gì.
     *
     * ĐƯỢC KIỂM TRA THẬT lúc đặt hàng, không chỉ hiện ra cho đẹp — xem
     * CouponService::resolve(). Điều kiện ghi trên giấy mà không ai chặn
     * thì tệ hơn không ghi.
     */
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->foreignId('promotion_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();

            $table->json('payment_methods')->nullable()->after('per_user_limit');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn('payment_methods');
        });
    }
};
