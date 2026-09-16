<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('kind', 20)->default('plant')->after('slug');

            $table->index('kind', 'categories_kind_index');
        });

        \Illuminate\Support\Facades\DB::table('categories')
            ->whereIn('slug', ['phu-kien', 'vat-tu-cham-soc'])
            ->update(['kind' => 'supply']);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_kind_index');
            $table->dropColumn('kind');
        });
    }
};
