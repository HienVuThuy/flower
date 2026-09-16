<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** NHÓM THUẾ SUẤT — thay cho "một mức thuế cho toàn cửa hàng". */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_classes', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100);

            $table->string('code', 40)->unique();

            $table->decimal('rate', 6, 5)->nullable();

            $table->string('note', 300)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('tax_class_id')
                ->nullable()
                ->after('sale_ends_at')
                ->constrained('tax_classes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['tax_class_id']);
            $table->dropColumn('tax_class_id');
        });

        Schema::dropIfExists('tax_classes');
    }
};
