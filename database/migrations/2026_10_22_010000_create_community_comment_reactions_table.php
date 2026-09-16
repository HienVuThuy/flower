<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cảm xúc cho BÌNH LUẬN (bài đã có ở community_post_likes). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_comment_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reaction', 20)->default('thich');
            $table->timestamp('created_at')->nullable();

            $table->unique(['community_comment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_comment_reactions');
    }
};
