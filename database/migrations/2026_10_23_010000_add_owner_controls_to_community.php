<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Chủ bài tự quản lý bài của mình. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->timestamp('author_hidden_at')->nullable()->after('hidden_reason');
            $table->timestamp('comments_locked_at')->nullable()->after('author_hidden_at');
            $table->timestamp('pinned_at')->nullable()->after('comments_locked_at');
        });

        Schema::table('community_comments', function (Blueprint $table) {
            $table->foreignId('hidden_by')->nullable()->after('hidden_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('community_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hidden_by');
        });

        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropColumn(['author_hidden_at', 'comments_locked_at', 'pinned_at']);
        });
    }
};
