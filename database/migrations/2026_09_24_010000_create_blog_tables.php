<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cẩm nang — blog kiến thức về hoa và cây cảnh. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->string('description', 300)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('blog_category_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title', 200);
            $table->string('slug', 220)->unique();

            $table->string('excerpt', 300)->nullable();

            $table->longText('body');
            $table->string('cover_image')->nullable();

            $table->timestamp('published_at')->nullable();

            $table->string('meta_title', 200)->nullable();
            $table->string('meta_description', 300)->nullable();

            $table->unsignedInteger('view_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['published_at', 'blog_category_id']);
        });

        Schema::create('blog_post_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('note', 200)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unique(['blog_post_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_product');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_categories');
    }
};
