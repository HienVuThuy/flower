<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'hoa-khai-truong-su-kien';

    private const PRODUCT_SLUG = 'lang-hoa-khai-truong';

    public function up(): void
    {
        $now = now();

        $id = DB::table('categories')->where('slug', self::SLUG)->value('id');

        if (! $id) {
            $id = DB::table('categories')->insertGetId([
                'name' => 'Hoa khai trương & sự kiện',
                'slug' => self::SLUG,
                'description' => 'Lẵng hoa, kệ hoa chúc mừng khai trương, hội nghị và sự kiện.',
                'is_active' => true,
                'sort_order' => 55,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $hoaId = DB::table('categories')->where('slug', 'hoa')->value('id');

        if ($hoaId) {
            DB::table('products')
                ->where('slug', self::PRODUCT_SLUG)
                ->where('category_id', $hoaId)
                ->update(['category_id' => $id, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        $id = DB::table('categories')->where('slug', self::SLUG)->value('id');

        if (! $id) {
            return;
        }

        $hoaId = DB::table('categories')->where('slug', 'hoa')->value('id');

        if ($hoaId) {
            DB::table('products')
                ->where('category_id', $id)
                ->update(['category_id' => $hoaId, 'updated_at' => now()]);
        }

        if (DB::table('products')->where('category_id', $id)->doesntExist()) {
            DB::table('categories')->where('id', $id)->delete();
        }
    }
};
