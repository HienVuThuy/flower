<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NHẬT KÝ CÁ NHÂN — ba bảng, và mỗi bảng có một lý do riêng.
 * ⚠️ ĐÂY LÀ DỮ LIỆU RIÊNG TƯ CỦA TỪNG NGƯỜI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->string('kind', 20);
            $table->text('description')->nullable();

            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->string('cover_image')->nullable();
            $table->date('started_at')->nullable();

            $table->string('chart_metric')->nullable();

            $table->string('target_metric')->nullable();
            $table->decimal('target_value', 14, 2)->nullable();
            $table->string('target_unit', 20)->nullable();
            $table->date('target_date')->nullable();

            $table->boolean('is_archived')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'is_archived', 'updated_at']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();

            $table->date('entry_date');

            $table->string('title')->nullable();
            $table->text('body')->nullable();

            $table->string('condition', 20)->nullable();

            $table->string('photo')->nullable();

            $table->timestamps();

            $table->index(['journal_id', 'entry_date']);
        });

        Schema::create('journal_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();

            $table->string('name');

            $table->decimal('value', 14, 2);

            $table->string('unit', 20)->nullable();

            $table->timestamps();

            $table->index(['journal_entry_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_metrics');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('journals');
    }
};
