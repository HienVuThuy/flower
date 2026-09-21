<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chăm cây hộ — giá theo TỪNG CÂY và YÊU CẦU THÊM:
 * - bảng giá chỉ là giá tham khảo; cửa hàng chốt giá riêng cho cây khi xác nhận (price_agreed_at);
 * - trong lúc chăm, khách (hoặc cửa hàng đề xuất) thêm việc: thay chậu, tạo dáng, xử lý sâu…
 *   mỗi việc cửa hàng báo giá riêng, khách đồng ý mới tính vào tiền phiếu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boarding_bookings', function (Blueprint $table) {
            $table->timestamp('price_agreed_at')->nullable()->after('yearly_price');
        });

        /* Phiếu đã được xác nhận trước khi có cột này: giá lúc xác nhận chính là giá đã chốt. */
        DB::table('boarding_bookings')
            ->whereNotIn('status', ['cho_duyet', 'tu_choi'])
            ->update(['price_agreed_at' => DB::raw('updated_at')]);

        Schema::create('boarding_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boarding_booking_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('customer_note', 500)->nullable();
            $table->string('proposed_by', 20);
            $table->decimal('price', 12, 2)->nullable();
            $table->string('shop_note', 500)->nullable();
            $table->string('status', 20);
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->index(['boarding_booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boarding_extras');

        Schema::table('boarding_bookings', function (Blueprint $table) {
            $table->dropColumn('price_agreed_at');
        });
    }
};
