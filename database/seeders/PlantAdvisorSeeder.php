<?php

namespace Database\Seeders;

use App\Enums\CareDifficulty;
use App\Enums\FengShuiElement;
use App\Enums\Placement;
use App\Enums\ProductType;
use App\Enums\SellingForm;
use App\Enums\TraitType;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * DỮ LIỆU MẪU cho trang tư vấn chọn cây và gợi ý mua kèm.
 * ============================================================
 * ⚠️ CỬA HÀNG PHẢI RÀ LẠI TRƯỚC KHI BÁN THẬT.
 *
 * NGUỒN CỦA TỪNG LOẠI DỮ LIỆU — ba loại có độ chắc chắn khác hẳn nhau,
 * và trộn chúng lại là cách nhanh nhất để mất tin cậy:
 *
 *   VỊ TRÍ ĐẶT & ĐỘ KHÓ — đặc tính trồng trọt phổ biến, tra được:
 *     lưỡi hổ và trầu bà chịu bóng nên sống được ở phòng tắm và hành
 *     lang; sen đá cần nắng trực tiếp nên hợp ban công; dương xỉ ưa ẩm
 *     nên hợp phòng tắm và bếp. Đây không phải ý kiến.
 *
 *   HỢP MỆNH — TẬP QUÁN VĂN HOÁ, không phải sự thật khoa học.
 *     Gán theo đúng MỘT quy ước duy nhất và nói rõ quy ước đó ra: màu
 *     chủ đạo của cây/hoa ứng với hành cùng màu (xanh lá→Mộc, trắng/ánh
 *     kim→Kim, xanh dương→Thuỷ, đỏ/hồng/tím→Hoả, vàng/nâu→Thổ), cộng
 *     thêm những cây đã có sẵn quy ước dân gian riêng (kim tiền với tiền
 *     bạc, lưỡi hổ với trừ tà).
 *     Cây không rơi vào quy ước nào thì ĐỂ TRỐNG. Bịa một mệnh cho cái
 *     cây là đúng thứ nguyên tắc "không bịa dữ liệu" cấm.
 *
 *   HOA CẮT CÀNH KHÔNG CÓ `placement` VÀ KHÔNG CÓ ĐỘ KHÓ.
 *     Bó hoa cắm ở đâu cũng được và tàn sau vài ngày dù chăm kiểu gì —
 *     gán vị trí đặt cho nó là thông tin sai. Chúng chỉ có `feng_shui`
 *     theo màu, vì khách vẫn hỏi "tặng hoa màu gì hợp mệnh".
 *
 * CHẠY LẠI NHIỀU LẦN ĐƯỢC: updateOrCreate theo slug và ghi đè trọn bộ
 * nhãn, nên không sinh bản sao.
 *
 *     php artisan db:seed --class=PlantAdvisorSeeder
 */
class PlantAdvisorSeeder extends Seeder
{
    /**
     * CÂY SỐNG — có vị trí đặt, có độ khó, có thể có mệnh.
     *
     * @var array<string, array{placement: list<Placement>, feng_shui: list<FengShuiElement>, difficulty: CareDifficulty}>
     */
    private const PLANT_TRAITS = [
        'monstera-deliciosa-chau-gom' => [
            'placement' => [Placement::LivingRoom, Placement::Office, Placement::Shop],
            'feng_shui' => [FengShuiElement::Moc],
            'difficulty' => CareDifficulty::Medium,
        ],
        'kim-tien-chau-su' => [
            'placement' => [Placement::LivingRoom, Placement::Office, Placement::Desk, Placement::Shop],
            // Cây kim tiền gắn với tiền bạc trong quan niệm dân gian;
            // lá dày xanh đậm nên thường được nói là hợp Mộc và Thổ.
            'feng_shui' => [FengShuiElement::Moc, FengShuiElement::Tho],
            'difficulty' => CareDifficulty::Easy,
        ],
        'luoi-ho-mini-de-ban' => [
            // Sansevieria chịu bóng và chịu khô rất tốt — sống được cả ở
            // phòng tắm, phòng ngủ lẫn hành lang tối.
            'placement' => [
                Placement::Desk, Placement::Office, Placement::Bedroom,
                Placement::Bathroom, Placement::Hallway,
            ],
            'feng_shui' => [FengShuiElement::Kim, FengShuiElement::Tho],
            'difficulty' => CareDifficulty::Easy,
        ],
        'trau-ba-leo-cot' => [
            // Trầu bà (Epipremnum) là cây chịu bóng kinh điển.
            'placement' => [
                Placement::LivingRoom, Placement::Office,
                Placement::Bathroom, Placement::Hallway, Placement::Kitchen,
            ],
            'feng_shui' => [FengShuiElement::Moc],
            'difficulty' => CareDifficulty::Easy,
        ],
        'sen-da-mix-chau-da' => [
            // Sen đá cần nắng trực tiếp; để trong nhà thiếu sáng là vươn
            // dài và thối gốc. Bệ cửa sổ là chỗ trong nhà duy nhất đủ sáng.
            'placement' => [Placement::Balcony, Placement::WindowSill, Placement::Desk],
            'feng_shui' => [FengShuiElement::Tho],
            'difficulty' => CareDifficulty::Easy,
        ],
        'bonsai-mai-chieu-thuy' => [
            'placement' => [Placement::Balcony, Placement::Garden],
            'feng_shui' => [FengShuiElement::Moc],
            'difficulty' => CareDifficulty::Hard,
        ],
        'set-qua-cay-de-ban-kem-thiep' => [
            'placement' => [Placement::Desk, Placement::Office],
            'feng_shui' => [],
            'difficulty' => CareDifficulty::Easy,
        ],
    ];

    /**
     * HOA CẮT CÀNH — chỉ gán mệnh theo màu chủ đạo.
     *
     * @var array<string, list<FengShuiElement>>
     */
    private const FLOWER_ELEMENTS = [
        // Đỏ -> Hoả.
        'hoa-hong-do-ecuador' => [FengShuiElement::Hoa],
        // Hồng -> Hoả.
        'hop-hoa-hong-pastel' => [FengShuiElement::Hoa],
        'canh-dao-phai-choi-tet' => [FengShuiElement::Hoa],
        // Trắng -> Kim.
        'gio-hoa-baby-trang' => [FengShuiElement::Kim],
        'hoa-cam-tay-co-dau' => [FengShuiElement::Kim],
        // Vàng/cam -> Thổ và Hoả.
        'huong-duong-ruc-ro' => [FengShuiElement::Tho, FengShuiElement::Hoa],
        /*
         * KHÔNG gán cho "Bó tulip Hà Lan" và "Lẵng hoa khai trương":
         * cả hai là hàng nhiều màu / làm theo yêu cầu, nên không có màu
         * chủ đạo để áp quy ước. Gán bừa một mệnh sẽ là thông tin sai.
         */
    ];

    /**
     * CÂY MẪU BỔ SUNG.
     *
     * ⚠️ Là dữ liệu mẫu do dự án đặt ra để trang tư vấn có đủ lựa chọn
     * cho mọi vị trí và mọi mệnh. Giá và mô tả không phải hàng cửa hàng
     * đang bán.
     *
     * Chọn đúng những loài lấp được chỗ trống thật:
     *   - dương xỉ  -> phòng tắm, bếp (ưa ẩm)
     *   - vạn niên thanh -> hành lang (chịu tối)
     *   - cẩm tú cầu xanh -> mệnh Thuỷ, trước đó KHÔNG cây nào có
     *   - lan hồ điệp tím -> mệnh Hoả cho cây chậu, và mức khó Trung bình
     */
    private const NEW_PLANTS = [
        [
            'name' => 'Dương xỉ Boston treo',
            'code' => 'CX-DUONGXI-01',
            'slug' => 'duong-xi-boston-treo',
            'price' => 165000,
            'short' => 'Tán lá rủ mềm, ưa ẩm — hợp phòng tắm và bếp.',
            'category' => 'cay-canh',
            'form' => SellingForm::Pot,
            'placement' => [Placement::Bathroom, Placement::Kitchen, Placement::Balcony],
            'feng_shui' => [FengShuiElement::Moc],
            'difficulty' => CareDifficulty::Medium,
            'care' => [
                'light' => 'Sáng gián tiếp, tránh nắng trực tiếp',
                'water' => 'Giữ đất luôn ẩm, phun sương lá mỗi ngày',
                'water_days' => 3,
                'fertilizer' => 'Bón loãng mỗi tháng vào mùa sinh trưởng',
                'fertilizer_days' => 30,
                'position' => 'Phòng tắm có cửa sổ, bếp thoáng',
            ],
        ],
        [
            'name' => 'Vạn niên thanh chậu sứ',
            'code' => 'CX-VANNIEN-01',
            'slug' => 'van-nien-thanh-chau-su',
            'price' => 195000,
            'short' => 'Chịu bóng rất tốt, sống được ở hành lang thiếu sáng.',
            'category' => 'cay-canh',
            'form' => SellingForm::Pot,
            'placement' => [Placement::Hallway, Placement::Office, Placement::LivingRoom, Placement::Bedroom],
            'feng_shui' => [FengShuiElement::Moc],
            'difficulty' => CareDifficulty::Easy,
            'care' => [
                'light' => 'Chịu được ánh sáng yếu, không cần nắng',
                'water' => 'Tưới khi mặt đất khô, khoảng 1 lần/tuần',
                'water_days' => 7,
                'fertilizer' => 'Bón NPK loãng mỗi 2 tháng',
                'fertilizer_days' => 60,
                'position' => 'Hành lang, góc phòng ít sáng',
                'notes' => 'Nhựa cây gây ngứa — để xa tầm trẻ nhỏ và thú nuôi.',
            ],
        ],
        [
            'name' => 'Kim ngân bện thân',
            'code' => 'CX-KIMNGAN-01',
            'slug' => 'kim-ngan-ben-than',
            'price' => 320000,
            'short' => 'Thân bện năm nhánh, dáng đứng — hay đặt ở quầy thu ngân.',
            'category' => 'cay-canh',
            'form' => SellingForm::Pot,
            'placement' => [Placement::LivingRoom, Placement::Shop, Placement::Office],
            // Kim ngân (Pachira) gắn với tài lộc trong quan niệm dân gian.
            'feng_shui' => [FengShuiElement::Moc, FengShuiElement::Tho],
            'difficulty' => CareDifficulty::Easy,
            'care' => [
                'light' => 'Sáng gián tiếp, chịu được đèn trong nhà',
                'water' => 'Tưới 1 lần/tuần, tránh úng gốc',
                'water_days' => 7,
                'fertilizer' => 'Bón NPK mỗi 2 tháng',
                'fertilizer_days' => 60,
                'position' => 'Quầy thu ngân, góc phòng khách',
            ],
        ],
        [
            'name' => 'Lan hồ điệp tím chậu sứ',
            'code' => 'CX-LANHODIEP-01',
            'slug' => 'lan-ho-diep-tim-chau-su',
            'price' => 650000,
            'short' => 'Hai cành hoa tím, bền 6–8 tuần nếu để đúng chỗ.',
            'category' => 'cay-canh',
            'form' => SellingForm::Pot,
            'placement' => [Placement::WindowSill, Placement::LivingRoom, Placement::Shop],
            // Tím -> Hoả theo quy ước màu.
            'feng_shui' => [FengShuiElement::Hoa],
            'difficulty' => CareDifficulty::Hard,
            'care' => [
                'light' => 'Sáng gián tiếp mạnh, tuyệt đối tránh nắng trưa',
                'water' => 'Ngâm gốc 10 phút mỗi 7–10 ngày, để ráo hẳn',
                'water_days' => 8,
                'fertilizer' => 'Phân lan pha loãng mỗi 2 tuần khi ra rễ mới',
                'fertilizer_days' => 14,
                'position' => 'Bệ cửa sổ hướng đông',
                'notes' => 'Không tưới vào ngồng hoa; nước đọng làm thối ngồng.',
            ],
        ],
        [
            'name' => 'Bó cẩm tú cầu xanh',
            'code' => 'HO-CAMTUCAU-01',
            'slug' => 'bo-cam-tu-cau-xanh',
            'price' => 480000,
            'short' => 'Cẩm tú cầu xanh lam, bó giấy mộc — màu hiếm trong hoa tươi.',
            'category' => 'hoa',
            'form' => SellingForm::Bouquet,
            'placement' => [],
            // Xanh dương -> Thuỷ. Đây là sản phẩm DUY NHẤT của mệnh này,
            // và đó là lý do nó được thêm vào.
            'feng_shui' => [FengShuiElement::Thuy],
            'difficulty' => null,
            'care' => [
                'water_change' => 'Thay nước mỗi ngày, cắm ngập 1/3 cành',
                'trim' => 'Cắt vát gốc 2 ngày/lần',
                'lifespan' => 'Tươi 5–7 ngày nếu để nơi mát',
            ],
        ],
    ];

    /**
     * PHỤ KIỆN — đồ dùng BỀN, mua một lần dùng lâu.
     */
    /*
     * PHỤ KIỆN VÀ VẬT TƯ ĐÃ CHUYỂN SANG SupplyCatalogSeeder.
     *
     * Chúng từng nằm ngay trong tệp này. Nhưng một seeder tên là "tư vấn
     * chọn cây" mà lại quyết định cửa hàng có những danh mục phụ kiện
     * nào thì không ai đi tìm ở đây — người muốn biết "vì sao chậu sứ
     * nằm ở danh mục này" sẽ mở CatalogSeeder ra trước, rồi bỏ cuộc.
     *
     * Tệp này giờ chỉ còn đúng việc của nó: gắn nhãn tư vấn (vị trí đặt,
     * hợp mệnh, độ khó chăm) cho cây.
     */

    public function run(): void
    {
        foreach (self::NEW_PLANTS as $spec) {
            $this->newPlant($spec);
        }

        foreach (self::PLANT_TRAITS as $slug => $spec) {
            $this->plant($slug, $spec);
        }

        foreach (self::FLOWER_ELEMENTS as $slug => $elements) {
            $this->flower($slug, $elements);
        }
    }


    /**
     * Cây mẫu bổ sung.
     *
     * @param  array<string, mixed>  $spec
     */
    private function newPlant(array $spec): void
    {
        $category = Category::where('slug', $spec['category'])->first();

        if (! $category) {
            $this->command?->warn("Bỏ qua '{$spec['slug']}': không có danh mục '{$spec['category']}'.");

            return;
        }

        $care = $spec['care'];

        if ($spec['difficulty'] instanceof CareDifficulty) {
            $care['difficulty'] = $spec['difficulty']->value;
        }

        $product = Product::updateOrCreate(
            ['slug' => $spec['slug']],
            [
                'category_id' => $category->id,
                'name' => $spec['name'],
                'product_code' => $spec['code'],
                'short_description' => $spec['short'],
                'product_type' => $spec['form'] === SellingForm::Bouquet
                    ? ProductType::Flower
                    : ProductType::Plant,
                'selling_form' => $spec['form'],
                'base_price' => $spec['price'],
                'care_info' => $care,
                'status' => 'active',
                'track_inventory' => true,
                'stock_quantity' => 20,
            ],
        );

        $product->syncTraits(
            TraitType::Placement,
            array_map(fn (Placement $p) => $p->value, $spec['placement']),
        );

        $product->syncTraits(
            TraitType::FengShui,
            array_map(fn (FengShuiElement $e) => $e->value, $spec['feng_shui']),
        );
    }

    /**
     * @param  array{placement: list<Placement>, feng_shui: list<FengShuiElement>, difficulty: CareDifficulty}  $spec
     */
    private function plant(string $slug, array $spec): void
    {
        $product = Product::where('slug', $slug)->first();

        if (! $product) {
            // Seeder này GÁN NHÃN cho hàng có thật, không phải nơi sinh ra
            // hàng. Thiếu sản phẩm thì báo rồi bỏ qua.
            $this->command?->warn("Bỏ qua: không có sản phẩm slug '{$slug}'.");

            return;
        }

        $product->syncTraits(
            TraitType::Placement,
            array_map(fn (Placement $p) => $p->value, $spec['placement']),
        );

        $product->syncTraits(
            TraitType::FengShui,
            array_map(fn (FengShuiElement $e) => $e->value, $spec['feng_shui']),
        );

        /*
         * Độ khó nằm trong care_info (JSON), không phải bảng nhãn.
         *
         * Giữ nguyên các khoá care_info khác đã có: ghi đè cả mảng sẽ xoá
         * mất phần ánh sáng/nước/đất mà admin đã nhập.
         */
        $care = is_array($product->care_info) ? $product->care_info : [];
        $care['difficulty'] = $spec['difficulty']->value;

        $product->update(['care_info' => $care]);
    }

    /**
     * Hoa cắt cành: CHỈ gán mệnh, không gán vị trí đặt.
     *
     * @param  list<FengShuiElement>  $elements
     */
    private function flower(string $slug, array $elements): void
    {
        $product = Product::where('slug', $slug)->first();

        if (! $product) {
            $this->command?->warn("Bỏ qua: không có sản phẩm slug '{$slug}'.");

            return;
        }

        $product->syncTraits(
            TraitType::FengShui,
            array_map(fn (FengShuiElement $e) => $e->value, $elements),
        );

        // Xoá vị trí đặt nếu lần chạy trước có gán nhầm.
        $product->syncTraits(TraitType::Placement, []);
    }
}
