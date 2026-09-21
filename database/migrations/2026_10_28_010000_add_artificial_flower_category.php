<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Danh mục "Hoa giả": hàng chính nhưng KHÔNG phải hoa tươi — không theo lô, không héo,
 * không đổi trả vì héo. Chưa có sản phẩm thì cửa hàng tự ẩn danh mục này.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('categories')->where('slug', 'hoa-gia')->exists()) {
            return;
        }

        DB::table('categories')->insert([
            'name' => 'Hoa giả',
            'slug' => 'hoa-gia',
            'kind' => 'plant',
            'description' => 'Hoa sáp, hoa lụa, hoa giấy — giữ dáng lâu, không cần tưới, hợp để bàn làm việc, phòng máy lạnh và người hay đi xa.',
            'is_active' => true,
            'sort_order' => 65,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $id = DB::table('categories')->where('slug', 'hoa-gia')->value('id');

        if ($id && ! DB::table('products')->where('category_id', $id)->exists()) {
            DB::table('categories')->where('id', $id)->delete();
        }
    }
};
