<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Danh mục "Hoa khai trương & sự kiện" + xếp lẵng hoa vào đó.
     * ============================================================
     * VIỆC CÒN DỞ TỪ QĐ-08.
     *
     * Khi tách trục dịp ra khỏi cột product_type, ba trong bốn sản phẩm
     * giữ được thông tin dịp vì Category đã ghi sẵn:
     *     wedding -> "Hoa cưới"        gift -> "Hoa quà tặng"
     *
     * Riêng "Lẵng hoa khai trương" mất nhãn "Hoa sự kiện" vì không có
     * danh mục nào tương ứng — nó nằm ở danh mục "Hoa" chung chung.
     * Migration này bù đúng chỗ thiếu đó, để trục dịp nhất quán:
     *
     *     Hoa cưới (50) - Hoa khai trương & sự kiện (55) - Hoa quà tặng (60)
     *
     * Đây là danh mục CÓ NGHIỆP VỤ THẬT, không phải thêm cho đủ bộ:
     * cửa hàng đã có sẵn tính năng "Sự kiện & số lượng lớn" nhận yêu cầu
     * báo giá, tức là mảng sự kiện vốn nằm trong việc kinh doanh.
     */
    private const SLUG = 'hoa-khai-truong-su-kien';

    private const PRODUCT_SLUG = 'lang-hoa-khai-truong';

    public function up(): void
    {
        $now = now();

        // firstOrCreate thủ công: migration chạy lại trên máy đã có sẵn
        // danh mục này thì không được tạo bản thứ hai.
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

        /*
         * Chỉ chuyển sản phẩm nếu nó đang ở danh mục "Hoa" — tức là vẫn
         * đúng chỗ mà QĐ-08 để lại. Cửa hàng đã tự xếp nó đi đâu khác
         * thì ý của họ mới hơn ý của migration, không được ghi đè.
         */
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

        // Trả sản phẩm về "Hoa" TRƯỚC khi xoá danh mục, nếu không khoá
        // ngoại sẽ chặn (hoặc tệ hơn: xoá lây sang sản phẩm).
        if ($hoaId) {
            DB::table('products')
                ->where('category_id', $id)
                ->update(['category_id' => $hoaId, 'updated_at' => now()]);
        }

        /*
         * Chỉ xoá khi KHÔNG CÒN sản phẩm nào trong danh mục. Cửa hàng có
         * thể đã xếp thêm hàng vào đây sau khi migration chạy; xoá lúc đó
         * là kéo theo dữ liệu của người ta.
         */
        if (DB::table('products')->where('category_id', $id)->doesntExist()) {
            DB::table('categories')->where('id', $id)->delete();
        }
    }
};
