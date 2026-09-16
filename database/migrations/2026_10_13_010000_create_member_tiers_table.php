<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Hạng thành viên — ngưỡng và quyền lợi CẤU HÌNH ĐƯỢC. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 50);

            $table->decimal('min_spend', 14, 2);

            $table->decimal('discount_percent', 5, 2)->default(0);

            $table->decimal('free_shipping_from', 14, 2)->nullable();

            $table->unsignedSmallInteger('bonus_points_percent')->default(0);

            $table->timestamps();
        });

        $bayGio = now();

        DB::table('member_tiers')->insert([
            ['code' => 'mam', 'name' => 'Mầm', 'min_spend' => 0, 'discount_percent' => 0, 'free_shipping_from' => null, 'bonus_points_percent' => 0],
            ['code' => 'la', 'name' => 'Lá', 'min_spend' => 2000000, 'discount_percent' => 0, 'free_shipping_from' => null, 'bonus_points_percent' => 5],
            ['code' => 'hoa', 'name' => 'Hoa', 'min_spend' => 5000000, 'discount_percent' => 2, 'free_shipping_from' => null, 'bonus_points_percent' => 10],
            ['code' => 'vuon', 'name' => 'Vườn', 'min_spend' => 15000000, 'discount_percent' => 3, 'free_shipping_from' => 300000, 'bonus_points_percent' => 15],
            ['code' => 'rung', 'name' => 'Rừng', 'min_spend' => 30000000, 'discount_percent' => 5, 'free_shipping_from' => 0, 'bonus_points_percent' => 20],
        ]);

        DB::table('member_tiers')->update(['created_at' => $bayGio, 'updated_at' => $bayGio]);
    }

    public function down(): void
    {
        Schema::dropIfExists('member_tiers');
    }
};
