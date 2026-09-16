<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Thư viện sản phẩm nhận thêm VIDEO, không chỉ ảnh. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('kind', 10)->default('image')->after('product_id');

            $table->string('video_url', 500)->nullable()->after('path');
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->string('path')->nullable()->change();
        });
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('product_images')->whereNull('path')->delete();

        Schema::table('product_images', function (Blueprint $table) {
            $table->string('path')->nullable(false)->change();
            $table->dropColumn(['kind', 'video_url']);
        });
    }
};
