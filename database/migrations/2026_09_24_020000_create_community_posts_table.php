<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Góc cây của bạn" — mạng xã hội NHẸ, cố ý nhẹ.
 * ⚠️ ẢNH Ở ĐÂY LÀ ẢNH CÔNG KHAI — METADATA PHẢI BỊ TƯỚC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_posts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('body', 1000);
            $table->string('photo')->nullable();

            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->string('reject_reason', 200)->nullable();

            $table->timestamps();

            $table->index(['approved_at', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_posts');
    }
};
