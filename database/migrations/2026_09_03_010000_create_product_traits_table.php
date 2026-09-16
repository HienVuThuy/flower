<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_traits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('trait_type', 32);
            $table->string('trait_value', 32);

            $table->timestamps();

            $table->unique(['product_id', 'trait_type', 'trait_value'], 'product_traits_unique');

            $table->index(['trait_type', 'trait_value'], 'product_traits_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_traits');
    }
};
