<?php

use App\Enums\CareProfile;
use App\Enums\SellingForm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tách trục DỊP ra khỏi cột product_type.
     * ============================================================
     * Cột này đang chứa lẫn hai câu trả lời khác nhau:
     *
     *   'flower', 'plant'              — món hàng này LÀ gì
     *   'event', 'wedding', 'gift'     — mua để LÀM GÌ  (trục dịp)
     *
     * Trục dịp đã có nhà riêng là Category ("Hoa cưới", "Hoa quà tặng"),
     * nên để chung ở đây là ghi cùng một sự thật ở hai nơi. Hậu quả nhìn
     * thấy được trên trang sản phẩm:
     *
     *     Danh mục      : Hoa cưới
     *     Loại sản phẩm : Hoa cưới     ← trùng từng chữ
     *
     * KHÔNG tạo cột `occasion` mới: 3 trong 4 dòng có dịp thì Category đã
     * ghi rồi, thêm cột nữa là nhân bản lần thứ ba.
     *
     * CHUYỂN THEO SLUG, không theo id: id không giống nhau giữa các máy,
     * còn slug là định danh nghiệp vụ ổn định (seeder cũng dùng slug).
     * Nhờ vậy down() đảo ngược được CHÍNH XÁC từng dòng.
     */
    private const MAP = [
        // slug => [giá trị mới, giá trị cũ]
        'lang-hoa-khai-truong'         => ['flower', 'event'],
        'hoa-cam-tay-co-dau'           => ['flower', 'wedding'],
        'hop-hoa-hong-pastel'          => ['flower', 'gift'],
        'set-qua-cay-de-ban-kem-thiep' => ['plant',  'gift'],
    ];

    public function up(): void
    {
        foreach (self::MAP as $slug => [$new, $old]) {
            DB::table('products')
                ->where('slug', $slug)
                ->where('product_type', $old)
                ->update(['product_type' => $new]);
        }

        /*
         * QUÉT DỌN BẮT BUỘC.
         *
         * Ngay sau migration này, cột được cast sang App\Enums\ProductType.
         * Cast khiến MỌI giá trị lạ còn sót lại trở thành lỗi chết người:
         * đọc bản ghi đó lên là ValueError, trang sản phẩm sập.
         *
         * Bảng MAP ở trên chỉ biết những sản phẩm của seeder. Dòng do
         * cửa hàng tự thêm (hoặc máy khác) có thể vẫn mang 'event',
         * 'wedding', 'gift'. Đoạn này bảo đảm không còn giá trị nào nằm
         * ngoài enum, suy ra từ hình thức bán:
         *   - cây còn sống (chậu / nguyên bản)  -> 'plant'
         *   - còn lại                            -> 'flower'
         */
        $legacy = DB::table('products')
            ->whereNotIn('product_type', ['flower', 'plant', 'other'])
            ->get(['id', 'selling_form']);

        foreach ($legacy as $row) {
            $isLivingPlant = SellingForm::tryFrom((string) $row->selling_form)
                ?->careProfile() === CareProfile::LivingPlant;

            DB::table('products')
                ->where('id', $row->id)
                ->update(['product_type' => $isLivingPlant ? 'plant' : 'flower']);
        }
    }

    public function down(): void
    {
        /*
         * Trả lại đúng giá trị cũ cho những dòng migration này đã đổi.
         *
         * Điều kiện `where('product_type', $new)` để không ghi đè khi cửa
         * hàng đã tự sửa loại sản phẩm sau khi migration chạy — lúc đó ý
         * muốn của người dùng mới hơn ý muốn của migration.
         *
         * Các dòng bị đoạn "quét dọn" ở up() sửa thì KHÔNG khôi phục được:
         * giá trị cũ của chúng không được ghi lại ở đâu cả. Đây là giới
         * hạn có thật, ghi ra để người chạy rollback biết trước.
         */
        foreach (self::MAP as $slug => [$new, $old]) {
            DB::table('products')
                ->where('slug', $slug)
                ->where('product_type', $new)
                ->update(['product_type' => $old]);
        }
    }
};
