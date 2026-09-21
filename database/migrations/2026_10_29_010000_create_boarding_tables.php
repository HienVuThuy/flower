<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CHĂM CÂY HỘ: khách gửi cây cho cửa hàng chăm trong một khoảng thời gian,
 * cửa hàng trả lại đúng hẹn (hoặc đúng dịp, ví dụ trước Tết) và có thể lặp lại mỗi năm.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* Bảng giá do admin tự đặt theo loại cây / độ khó / cỡ cây. */
        Schema::create('boarding_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->string('care_difficulty', 20)->nullable();
            $table->decimal('monthly_price', 12, 2);
            $table->decimal('yearly_price', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        /* Đợt trả cây theo dịp: admin đặt ngày thật mỗi năm (không đoán ngày âm lịch). */
        Schema::create('boarding_windows', function (Blueprint $table) {
            $table->id();
            $table->string('group_key', 40);
            $table->string('name', 120);
            $table->date('return_on');
            $table->date('take_back_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['group_key', 'return_on']);
        });

        Schema::create('boarding_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('boarding_rate_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('boarding_bookings')->nullOnDelete();
            $table->string('plant_name', 150);
            $table->string('plant_note', 500)->nullable();
            $table->string('photo')->nullable();

            $table->string('mode', 20);
            $table->unsignedSmallInteger('months')->nullable();
            $table->unsignedSmallInteger('years')->nullable();
            $table->foreignId('boarding_window_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('repeat_yearly')->default(false);
            $table->boolean('waiting_next_window')->default(false);

            $table->date('drop_off_on');
            $table->date('return_on')->nullable();
            $table->date('received_on')->nullable();
            $table->date('returned_on')->nullable();
            $table->boolean('early_return')->default(false);

            $table->string('handover', 20);
            $table->string('contact_phone', 20);
            $table->string('address', 255)->nullable();
            $table->string('customer_note', 500)->nullable();

            $table->decimal('monthly_price', 12, 2);
            $table->decimal('yearly_price', 12, 2);
            $table->decimal('care_amount', 12, 2);
            $table->decimal('handover_fee', 12, 2)->default(0);
            $table->decimal('rush_fee', 12, 2)->default(0);
            $table->decimal('adjustment', 12, 2)->default(0);
            $table->string('adjustment_reason', 255)->nullable();
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->timestamp('paid_at')->nullable();

            $table->string('status', 20);
            $table->string('reject_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'return_on']);
            $table->index(['user_id', 'status']);
        });

        /* Nhật ký phiếu: đổi trạng thái và ảnh / ghi chú chăm sóc cửa hàng gửi cho khách xem. */
        Schema::create('boarding_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boarding_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20);
            $table->string('status', 20)->nullable();
            $table->string('note', 1000)->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();
        });

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->foreignId('boarding_booking_id')->nullable()->after('product_id')
                ->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('boarding_booking_id');
        });

        Schema::dropIfExists('boarding_events');
        Schema::dropIfExists('boarding_bookings');
        Schema::dropIfExists('boarding_windows');
        Schema::dropIfExists('boarding_rates');
    }
};
