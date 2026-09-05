<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * GIÁ LINH HOẠT THEO THỜI ĐIỂM cho chương trình khuyến mại.
     * ============================================================
     * VÌ SAO NGÀNH HOA CẦN THỨ NÀY, KHÁC NGÀNH KHÁC:
     * Hoa tươi là hàng hỏng theo giờ. Bó hoa còn trên kệ lúc 20h tối
     * không phải "hàng tồn" — sáng mai nó không bán được nữa. Bán rẻ 30%
     * lúc 19h là thu về 70% thay vì mất trắng. Ngược lại, tuần cận
     * Valentine hay 8/3 thì cầu vượt cung và giá lên là chuyện bình
     * thường của thị trường.
     *
     * `starts_at`/`ends_at` sẵn có chỉ khai được MỘT KHOẢNG LIÊN TỤC
     * ("từ 1/2 tới 14/2"). Không khai được "mỗi ngày từ 19h tới 22h" —
     * mà đó chính là chương trình xả hàng cuối ngày, thứ phải lặp lại
     * hằng ngày.
     *
     * HAI CỘT MỚI, và cả hai đều LỌC THÊM chứ không thay thế:
     *
     *   daily_start_time / daily_end_time
     *     Khung giờ trong ngày. Bỏ trống = cả ngày (hành vi cũ, nên mọi
     *     chương trình đang chạy không đổi gì).
     *
     *   weekdays
     *     Mảng thứ trong tuần (1=Thứ Hai ... 7=Chủ Nhật, theo ISO-8601).
     *     Bỏ trống = mọi ngày. Dùng cho "giảm giá ngày thường" hoặc
     *     "phụ thu cuối tuần".
     *
     * KHÔNG tạo bảng riêng: đây là ĐIỀU KIỆN ÁP DỤNG của một chương
     * trình, không phải một thực thể có đời sống riêng. Chương trình bị
     * xoá thì khung giờ của nó cũng vô nghĩa.
     *
     * TĂNG GIÁ thì làm thế nào? Không có "khuyến mại âm" — dịp cao điểm
     * thì cửa hàng đặt `base_price` theo giá cao điểm và chạy chương
     * trình giảm vào những khung giờ/ngày thấp điểm. Cách này giữ được
     * một luật duy nhất "giá cuối ≤ giá gốc" mà PricingService đang bảo
     * vệ — bỏ luật đó là mở đường cho giá nhảy lung tung vì cấu hình sai.
     */
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->time('daily_start_time')->nullable()->after('ends_at');
            $table->time('daily_end_time')->nullable()->after('daily_start_time');
            $table->json('weekdays')->nullable()->after('daily_end_time');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['daily_start_time', 'daily_end_time', 'weekdays']);
        });
    }
};
