<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Chi phí vận hành — những khoản tiền ra mà hệ thống chưa có số. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            $table->date('spent_on');

            $table->string('category', 30);
            $table->string('description', 200);

            $table->decimal('amount', 14, 2);

            $table->string('payment_method', 20)->nullable();
            $table->boolean('is_fixed')->default(false);
            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_name')->nullable();

            $table->timestamps();

            $table->index(['spent_on', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
