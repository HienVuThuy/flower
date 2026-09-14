<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chuỗi ngày ghé thăm — xem App\Services\Points\VisitStreak.
 *
 * Hai cột trên `users` chứ không phải một bảng mỗi ngày một dòng: câu hỏi
 * duy nhất cần trả lời là "hôm qua có ghé không", và một bảng nhật ký ghé
 * thăm là thứ dữ liệu theo dõi hành vi không ai hứa với khách là sẽ giữ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('visit_streak')->default(0);

            // Ngày theo lịch Việt Nam, không phải thời điểm.
            $table->date('last_visit_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['visit_streak', 'last_visit_on']);
        });
    }
};
