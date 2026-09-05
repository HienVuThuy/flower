<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();

            $table->string('name', 180);

            $table->string('slug', 220)
                ->unique();

            $table->string('product_code', 80)
                ->unique();

            $table->string('short_description', 500)
                ->nullable();

            $table->text('description')
                ->nullable();

            $table->string('product_type', 50);

            $table->string('selling_form', 50);

            $table->decimal('base_price', 15, 2)
                ->nullable();

            $table->string('main_image')
                ->nullable();

            $table->string('status', 30)
                ->default('draft');

            $table->string('meta_title', 255)
                ->nullable();

            $table->string('meta_description', 500)
                ->nullable();

            $table->timestamps();

            $table->softDeletes();

            $table->index('category_id');
            $table->index('product_type');
            $table->index('selling_form');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};