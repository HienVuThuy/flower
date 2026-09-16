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
 * ⚠️ CỬA HÀNG PHẢI RÀ LẠI TRƯỚC KHI BÁN THẬT.
 */
class PlantAdvisorSeeder extends Seeder
{
    private const PLANT_TRAITS = [
        'monstera-deliciosa-chau-gom' => [
            'placement' => [Placement::LivingRoom, Placement::Office, Placement::Shop],
            'feng_shui' => [FengShuiElement::Moc],
            'difficulty' => CareDifficulty::Medium,
        ],
        'kim-tien-chau-su' => [
            'placement' => [Placement::LivingRoom, Placement::Office, Placement::Desk, Placement::Shop],
            'feng_shui' => [FengShuiElement::Moc, FengShuiElement::Tho],
            'difficulty' => CareDifficulty::Easy,
        ],
        'luoi-ho-mini-de-ban' => [
            'placement' => [
                Placement::Desk, Placement::Office, Placement::Bedroom,
                Placement::Bathroom, Placement::Hallway,
            ],
            'feng_shui' => [FengShuiElement::Kim, FengShuiElement::Tho],
            'difficulty' => CareDifficulty::Easy,
        ],
        'trau-ba-leo-cot' => [
            'placement' => [
                Placement::LivingRoom, Placement::Office,
                Placement::Bathroom, Placement::Hallway, Placement::Kitchen,
            ],
            'feng_shui' => [FengShuiElement::Moc],
            'difficulty' => CareDifficulty::Easy,
        ],
        'sen-da-mix-chau-da' => [
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

    private const FLOWER_ELEMENTS = [
        'hoa-hong-do-ecuador' => [FengShuiElement::Hoa],
        'hop-hoa-hong-pastel' => [FengShuiElement::Hoa],
        'canh-dao-phai-choi-tet' => [FengShuiElement::Hoa],
        'gio-hoa-baby-trang' => [FengShuiElement::Kim],
        'hoa-cam-tay-co-dau' => [FengShuiElement::Kim],
        'huong-duong-ruc-ro' => [FengShuiElement::Tho, FengShuiElement::Hoa],
    ];

    /** ⚠️ Là dữ liệu mẫu do dự án đặt ra để trang tư vấn có đủ lựa chọn cho mọi vị trí và mọi mệnh. */
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
            'feng_shui' => [FengShuiElement::Thuy],
            'difficulty' => null,
            'care' => [
                'water_change' => 'Thay nước mỗi ngày, cắm ngập 1/3 cành',
                'trim' => 'Cắt vát gốc 2 ngày/lần',
                'lifespan' => 'Tươi 5–7 ngày nếu để nơi mát',
            ],
        ],
    ];

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

    private function plant(string $slug, array $spec): void
    {
        $product = Product::where('slug', $slug)->first();

        if (! $product) {
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

        $care = is_array($product->care_info) ? $product->care_info : [];
        $care['difficulty'] = $spec['difficulty']->value;

        $product->update(['care_info' => $care]);
    }

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

        $product->syncTraits(TraitType::Placement, []);
    }
}
