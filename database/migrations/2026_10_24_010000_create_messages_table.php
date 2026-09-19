<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tin nhắn trực tiếp giữa khách và cửa hàng: mỗi khách một hội thoại, nhân viên nào cũng trả lời được. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('content');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'id']);
            $table->index(['customer_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
