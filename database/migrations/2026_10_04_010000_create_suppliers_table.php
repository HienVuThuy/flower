<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** NƠI CỬA HÀNG LẤY HÀNG — thành một bảng, không còn là ô chữ tự do. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            $table->string('name', 160);

            $table->unique('name');

            $table->string('kind', 32)->default('khac');

            $table->string('phone', 30)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('address', 255)->nullable();

            $table->text('note')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['is_active', 'kind']);
        });

        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('code')
                ->constrained()
                ->nullOnDelete();

            $table->index('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });

        Schema::dropIfExists('suppliers');
    }
};
