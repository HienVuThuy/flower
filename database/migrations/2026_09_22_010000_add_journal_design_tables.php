<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhật ký: trang trí sổ, và cấu trúc riêng cho từng loại sổ.
 * ⚠️ VẪN LÀ DỮ LIỆU RIÊNG TƯ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->string('theme_key', 30)->nullable()->after('cover_image');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('sticker', 30)->nullable()->after('photo');

            $table->json('data')->nullable()->after('sticker');
        });

        Schema::create('journal_milestones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('journal_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title', 150);

            $table->timestamp('done_at')->nullable();

            $table->date('due_date')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['journal_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_milestones');

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['sticker', 'data']);
        });

        Schema::table('journals', function (Blueprint $table) {
            $table->dropColumn('theme_key');
        });
    }
};
