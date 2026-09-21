<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chăm cây hộ — BÁO GIÁ và TRAO ĐỔI:
 * - loại cây đặc thù (needs_quote), cây khách khai giá trị cao, cây có yêu cầu riêng → phải báo giá;
 * - mỗi lần báo giá là một phiên bản (dòng chi tiết + tổng), khách xác nhận / yêu cầu sửa / huỷ;
 * - tin nhắn chat khách ↔ nhân viên có thể gắn với một phiếu để trao đổi đúng việc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boarding_rates', function (Blueprint $table) {
            $table->boolean('needs_quote')->default(false)->after('yearly_price');
        });

        Schema::table('boarding_bookings', function (Blueprint $table) {
            $table->decimal('declared_value', 14, 2)->nullable()->after('plant_note');
        });

        Schema::create('boarding_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boarding_booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->json('lines');
            $table->decimal('total', 12, 2);
            $table->string('note', 1000)->nullable();
            $table->date('valid_until')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20);
            $table->string('response_note', 1000)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['boarding_booking_id', 'version']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('boarding_booking_id')->nullable()->after('sender_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('boarding_booking_id');
        });

        Schema::dropIfExists('boarding_quotes');

        Schema::table('boarding_bookings', function (Blueprint $table) {
            $table->dropColumn('declared_value');
        });

        Schema::table('boarding_rates', function (Blueprint $table) {
            $table->dropColumn('needs_quote');
        });
    }
};
