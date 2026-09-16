<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** HOA TƯƠI KHÔNG ĐẾM THEO CÁI — đếm theo LÔ. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flower_kinds', function (Blueprint $table) {
            $table->id();

            $table->string('name', 120)->unique();

            $table->string('default_unit', 16)->default('bo');

            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        Schema::create('flower_lots', function (Blueprint $table) {
            $table->id();

            $table->string('code', 32)->unique();

            $table->foreignId('flower_kind_id')->constrained()->restrictOnDelete();

            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();

            $table->string('supplier_name', 160)->nullable();

            $table->date('purchased_at');

            $table->decimal('quantity', 12, 2);
            $table->string('unit', 16);

            $table->decimal('total_cost', 12, 2);

            $table->string('status', 16)->default('dang_dung');
            $table->timestamp('closed_at')->nullable();

            $table->decimal('hao_hut', 12, 2)->default(0);

            $table->string('quality', 16)->nullable();

            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'purchased_at']);
            $table->index(['flower_kind_id', 'purchased_at']);
            $table->index('closed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flower_lots');
        Schema::dropIfExists('flower_kinds');
    }
};
