<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Thông báo trong trang cho khách. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('type', 30);

            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('community_post_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('community_comment_id')->nullable()->constrained('community_comments')->cascadeOnDelete();

            $table->string('note', 200)->nullable();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
