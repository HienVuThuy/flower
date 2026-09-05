<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * VÍ VOUCHER — mã giảm giá khách đã "lưu" về tài khoản.
     * ============================================================
     * VÌ SAO CẦN, KHI ĐÃ CÓ Ô NHẬP MÃ:
     * Ô nhập mã chỉ dùng được nếu khách BIẾT mã. Voucher hiện nay chủ yếu
     * đến từ trang khuyến mại, mà khách xem trang đó lúc chưa định mua —
     * tới lúc thanh toán thì đã quên mã. "Lưu" giữ hộ họ, và tới bước
     * thanh toán thì mã hiện sẵn thành danh sách để chọn.
     *
     * HAI CỘT MỚI TRÊN `coupons`:
     *
     *   is_public       - có hiện ở trang "Voucher của tôi" không.
     *                     Mã in trên tờ rơi hay gửi riêng cho một khách
     *                     thì KHÔNG được hiện công khai, nhưng vẫn phải
     *                     nhập tay được. Thiếu cột này thì mọi mã nội bộ
     *                     đều lộ ra cho tất cả mọi người.
     *
     *   per_user_limit  - một khách được dùng mã này mấy lần.
     *                     `usage_limit` sẵn có là giới hạn TỔNG trên toàn
     *                     hệ thống — hai chuyện khác hẳn nhau. Không có
     *                     cột này thì một người dùng hết sạch 100 lượt
     *                     của chương trình là hoàn toàn hợp lệ.
     *
     * BẢNG `coupon_user` GHI CẢ HAI VIỆC, và cố ý gộp:
     *   claimed_at - lúc bấm Lưu
     *   used_count - đã dùng bao nhiêu lần
     * Tách hai bảng thì mỗi lần kiểm tra "khách này còn dùng được không"
     * phải nối hai bảng, trong khi cả hai đều là "quan hệ giữa MỘT khách
     * và MỘT mã" — đúng một hàng.
     */
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('status');
            $table->unsignedSmallInteger('per_user_limit')->nullable()->after('usage_limit');
        });

        Schema::create('coupon_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();

            $table->timestamp('claimed_at');
            $table->unsignedSmallInteger('used_count')->default(0);

            $table->timestamps();

            /*
             * UNIQUE là phần quan trọng nhất của bảng này.
             *
             * Nút "Lưu" bấm nhanh hai lần, hoặc mở hai tab cùng bấm, sẽ
             * gửi hai request song song. Kiểm tra bằng PHP ("đã lưu chưa?"
             * rồi mới ghi) không chặn được: cả hai request đều đọc thấy
             * "chưa" trước khi bên nào kịp ghi. Chỉ ràng buộc ở tầng cơ sở
             * dữ liệu mới thật sự chặn — cùng cách đã dùng cho
             * orders.idempotency_key.
             */
            $table->unique(['user_id', 'coupon_id']);

            // Trang "Voucher của tôi" luôn lọc theo user rồi sắp theo thời
            // điểm lưu, mới nhất lên đầu.
            $table->index(['user_id', 'claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_user');

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['is_public', 'per_user_limit']);
        });
    }
};
