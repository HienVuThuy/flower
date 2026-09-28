<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Thư viện ảnh của bài Cẩm nang: admin tải ảnh lên rồi chèn vào thân bài. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_post_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('blog_post_id')
                ->constrained('blog_posts')
                ->cascadeOnDelete();

            $table->string('path');
            $table->string('alt', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['blog_post_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_images');
    }
};
