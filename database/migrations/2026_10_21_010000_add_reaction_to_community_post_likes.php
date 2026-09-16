<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lượt thích thành CẢM XÚC: thích, yêu thích, haha, wow, buồn. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_post_likes', function (Blueprint $table) {
            $table->string('reaction', 20)->default('thich')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('community_post_likes', function (Blueprint $table) {
            $table->dropColumn('reaction');
        });
    }
};
