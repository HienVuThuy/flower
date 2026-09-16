<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mô tả chi tiết theo KHỐI: chữ – ảnh – chữ – ảnh… */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('sort_order')->default(0);

            $table->string('kind', 10);

            $table->text('body')->nullable();

            $table->string('image_path')->nullable();
            $table->string('caption', 255)->nullable();

            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_blocks');
    }
};
