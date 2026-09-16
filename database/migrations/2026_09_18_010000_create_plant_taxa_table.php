<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cây phân loại sinh học của thực vật. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_taxa', function (Blueprint $table) {
            $table->id();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('plant_taxa')
                ->nullOnDelete();

            $table->string('rank', 20);

            $table->string('name');

            $table->string('scientific_name')->nullable();

            $table->string('slug')->unique();

            $table->text('description')->nullable();

            $table->string('image')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['parent_id', 'rank']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('taxon_id')
                ->nullable()
                ->after('category_id')
                ->constrained('plant_taxa')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['taxon_id']);
            $table->dropColumn('taxon_id');
        });

        Schema::dropIfExists('plant_taxa');
    }
};
