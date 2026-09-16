<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Dữ liệu mẫu cho cửa hàng hoa - cây cảnh. */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = $this->seedCategories();

        foreach ($this->products() as $data) {
            $this->seedProduct($data, $categories);
        }
    }

    private function seedCategories(): array
    {
        $rows = [
            ['Hoa', 'hoa', 'Hoa tươi cắt cành theo mùa.', 10],
            ['Cây cảnh', 'cay-canh', 'Cây trồng chậu trang trí nhà và văn phòng.', 20],
            ['Cây để bàn', 'cay-de-ban', 'Cây nhỏ đặt bàn làm việc, kệ sách.', 30],
            ['Cây bonsai', 'cay-bonsai', 'Cây dáng thế, tạo hình lâu năm.', 40],
            ['Hoa cưới', 'hoa-cuoi', 'Hoa cầm tay, hoa cài áo và trang trí tiệc cưới.', 50],
            ['Hoa khai trương & sự kiện', 'hoa-khai-truong-su-kien', 'Lẵng hoa, kệ hoa chúc mừng khai trương, hội nghị và sự kiện.', 55],
            ['Hoa quà tặng', 'hoa-qua-tang', 'Hộp hoa, set quà tặng dịp đặc biệt.', 60],
            ['Sen đá & Xương rồng', 'sen-da-xuong-rong', 'Cây mọng nước, dễ chăm, hợp người mới.', 70],
        ];

        $map = [];

        foreach ($rows as [$name, $slug, $description, $sort]) {
            $category = Category::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => $description,
                    'is_active' => true,
                    'sort_order' => $sort,
                ],
            );

            $map[$slug] = $category->id;
        }

        return $map;
    }

    private function seedProduct(array $data, array $categories): void
    {
        $slug = Str::slug($data['name']);

        if (Product::withTrashed()->where('slug', $slug)->exists()) {
            return;
        }

        $product = Product::create([
            'category_id' => $categories[$data['category']],
            'name' => $data['name'],
            'slug' => $slug,
            'product_code' => $data['code'],
            'short_description' => $data['short'],
            'description' => $data['description'],
            'product_type' => $data['type'],
            'selling_form' => $data['form'],
            'base_price' => $data['price'],
            'status' => $data['status'] ?? 'active',
            'track_inventory' => $data['track'] ?? false,
            'stock_quantity' => $data['stock'] ?? 0,
            'care_info' => $data['care'] ?? null,
        ]);

        foreach ($data['variants'] ?? [] as $i => $variant) {
            ProductVariant::create([
                'product_id' => $product->id,
                'name' => $variant['name'],
                'code' => $product->product_code . '-' . ($i + 1),
                'price' => $variant['price'] ?? null,
                'is_active' => true,
                'sort_order' => ($i + 1) * 10,
                'track_inventory' => $variant['track'] ?? false,
                'stock_quantity' => $variant['stock'] ?? 0,
            ]);
        }
    }

    private function products(): array
    {
        return [
            [
                'name' => 'Bó tulip Hà Lan',
                'code' => 'TL-HL-001',
                'category' => 'hoa',
                'type' => 'flower',
                'form' => 'bouquet',
                'price' => 520000,
                'track' => true,
                'stock' => 15,
                'short' => 'Tulip nhập khẩu, bó giấy kraft kèm nơ lụa.',
                'description' => "Tulip Hà Lan nhập theo lô, thân thẳng và nụ đều.\n\n"
                    . "Thích hợp tặng sinh nhật, chúc mừng hoặc trang trí bàn tiếp khách. "
                    . "Bó sẵn bằng giấy kraft, buộc nơ lụa cùng tông.",
                'care' => [
                    'water_change' => 'Thay nước mỗi ngày',
                    'trim' => 'Cắt vát gốc 1-2cm mỗi 2 ngày',
                    'placement' => 'Tránh nắng trực tiếp và gió quạt',
                    'lifespan' => '5 - 7 ngày',
                    'notes' => 'Tulip vẫn dài thêm sau khi cắt, nên để bình cao.',
                ],
                'variants' => [
                    ['name' => 'Bó 20 cành', 'price' => 520000, 'track' => true, 'stock' => 8],
                    ['name' => 'Bó 30 cành', 'price' => 740000, 'track' => true, 'stock' => 5],
                    ['name' => 'Bó 50 cành', 'price' => 1180000, 'track' => true, 'stock' => 2],
                ],
            ],

            [
                'name' => 'Hướng dương rực rỡ',
                'code' => 'HD-VN-001',
                'category' => 'hoa',
                'type' => 'flower',
                'form' => 'bouquet',
                'price' => 380000,
                'track' => false,
                'short' => 'Hoa hướng dương theo mùa, bó tròn kèm lá phụ.',
                'description' => "Hướng dương cắt trong ngày, bông to và cánh dày.\n\n"
                    . "Là hoa theo mùa nên số lượng thay đổi theo vụ; "
                    . "cửa hàng nhận đặt trước để giữ hàng.",
                'care' => [
                    'water_change' => 'Thay nước 2 ngày/lần',
                    'trim' => 'Cắt gốc khi thay nước',
                    'placement' => 'Nơi thoáng, tránh nắng gắt',
                    'lifespan' => '4 - 6 ngày',
                ],
            ],

            [
                'name' => 'Giỏ hoa baby trắng',
                'code' => 'BB-GI-001',
                'category' => 'hoa',
                'type' => 'flower',
                'form' => 'basket',
                'price' => 450000,
                'track' => true,
                'stock' => 8,
                'short' => 'Giỏ mây đan tay, baby trắng phủ đầy.',
                'description' => "Giỏ mây đan tay, cắm baby trắng phủ kín miệng giỏ.\n\n"
                    . "Phù hợp thăm hỏi, chúc mừng hoặc trang trí góc đọc sách.",
                'care' => [
                    'water_change' => 'Xịt ẩm nhẹ mỗi ngày',
                    'placement' => 'Nơi mát, tránh điều hoà thổi thẳng',
                    'lifespan' => '7 - 10 ngày',
                    'notes' => 'Baby để khô tự nhiên vẫn giữ dáng, dùng trang trí lâu dài.',
                ],
            ],

            [
                'name' => 'Cành đào phai chơi Tết',
                'code' => 'DA-PH-001',
                'category' => 'hoa',
                'type' => 'flower',
                'form' => 'branch',
                'price' => 950000,
                'track' => true,
                'stock' => 5,
                'short' => 'Cành đào phai dáng tự nhiên, nhiều nụ.',
                'description' => "Đào phai cắt cành, dáng tự nhiên, tỉ lệ nụ cao để nở dần trong Tết.\n\n"
                    . "Mỗi cành một dáng riêng, cửa hàng gửi ảnh thực tế trước khi giao.",
                'care' => [
                    'water_change' => 'Thay nước 3 ngày/lần',
                    'trim' => 'Đốt gốc rồi cắm nước ấm để nụ nở đều',
                    'placement' => 'Tránh đặt cạnh máy sưởi',
                    'lifespan' => '10 - 15 ngày',
                ],
            ],

            [
                'name' => 'Lẵng hoa khai trương',
                'code' => 'KT-LA-001',
                'category' => 'hoa-khai-truong-su-kien',
                'type' => 'flower',
                'form' => 'arrangement',
                'price' => null,
                'track' => false,
                'short' => 'Lẵng cao trang trí khai trương, làm theo yêu cầu.',
                'description' => "Lẵng hoa khai trương làm theo yêu cầu về kích thước, tông màu và nội dung thiệp.\n\n"
                    . "Giá phụ thuộc loại hoa và quy mô lẵng nên cửa hàng báo giá sau khi trao đổi.",
            ],

            [
                'name' => 'Hoa cầm tay cô dâu',
                'code' => 'HC-CD-001',
                'category' => 'hoa-cuoi',
                'type' => 'flower',
                'form' => 'bouquet',
                'price' => null,
                'track' => false,
                'short' => 'Hoa cầm tay thiết kế riêng theo váy cưới.',
                'description' => "Hoa cầm tay thiết kế riêng, phối theo màu váy và tông tiệc.\n\n"
                    . "Cần đặt trước tối thiểu 5 ngày để chuẩn bị hoa đúng màu.",
            ],

            [
                'name' => 'Hộp hoa hồng pastel',
                'code' => 'HQ-HP-001',
                'category' => 'hoa-qua-tang',
                'type' => 'flower',
                'form' => 'box',
                'price' => 690000,
                'track' => true,
                'stock' => 12,
                'short' => 'Hộp giấy cứng, hoa hồng tông pastel kèm thiệp.',
                'description' => "Hộp giấy cứng giữ dáng, hoa hồng tông pastel cắm sẵn trong mút giữ ẩm.\n\n"
                    . "Kèm thiệp viết tay theo nội dung khách gửi.",
                'care' => [
                    'water_change' => 'Xịt ẩm mút 2 ngày/lần',
                    'placement' => 'Để nơi mát, không cần cắm nước',
                    'lifespan' => '5 - 7 ngày',
                ],
                'variants' => [
                    ['name' => 'Hộp tròn', 'price' => 690000, 'track' => true, 'stock' => 7],
                    ['name' => 'Hộp vuông', 'price' => 720000, 'track' => true, 'stock' => 5],
                ],
            ],

            [
                'name' => 'Set quà cây để bàn kèm thiệp',
                'code' => 'SQ-CB-001',
                'category' => 'hoa-qua-tang',
                'type' => 'plant',
                'form' => 'set',
                'price' => 350000,
                'track' => true,
                'stock' => 10,
                'short' => 'Một cây để bàn, chậu sứ và thiệp trong hộp quà.',
                'description' => "Set gồm một cây để bàn, chậu sứ trắng và thiệp, đóng sẵn trong hộp quà.\n\n"
                    . "Thích hợp tặng đồng nghiệp dịp khai xuân hoặc tân gia.",
                'care' => [
                    'notes' => 'Xem hướng dẫn chăm sóc kèm trong hộp theo loại cây được chọn.',
                ],
            ],

            [
                'name' => 'Kim tiền chậu sứ',
                'code' => 'KT-SU-001',
                'category' => 'cay-canh',
                'type' => 'plant',
                'form' => 'pot',
                'price' => 480000,
                'track' => true,
                'stock' => 20,
                'short' => 'Cây kim tiền lá dày, chậu sứ men trắng.',
                'description' => "Kim tiền thân mọng, lá dày và bóng, chịu bóng bán phần rất tốt.\n\n"
                    . "Là cây văn phòng phổ biến vì ít phải tưới và hiếm khi rụng lá.",
                'care' => [
                    'light' => 'Ưa sáng gián tiếp, chịu được bóng bán phần',
                    'water' => 'Tưới 7 - 10 ngày/lần, để đất khô hẳn giữa hai lần',
                    'soil' => 'Đất tơi xốp trộn perlite, thoát nước nhanh',
                    'fertilizer' => 'Bón NPK loãng 2 tháng/lần',
                    'temperature' => '18°C - 30°C',
                    'position' => 'Bàn làm việc, góc phòng khách',
                    'frequency' => 'Kiểm tra độ ẩm đất hàng tuần',
                    'difficulty' => 'easy',
                    'notes' => 'Úng nước là nguyên nhân chết cây phổ biến nhất, thà để khô còn hơn tưới thừa.',
                ],
                'variants' => [
                    ['name' => 'Chậu sứ trắng', 'price' => 480000, 'track' => true, 'stock' => 9],
                    ['name' => 'Chậu sứ đen', 'price' => 480000, 'track' => true, 'stock' => 6],
                    ['name' => 'Chậu xi măng', 'price' => 520000, 'track' => true, 'stock' => 5],
                ],
            ],

            [
                'name' => 'Lưỡi hổ mini để bàn',
                'code' => 'LH-MN-001',
                'category' => 'cay-de-ban',
                'type' => 'plant',
                'form' => 'pot',
                'price' => 180000,
                'track' => true,
                'stock' => 30,
                'short' => 'Lưỡi hổ cỡ nhỏ, hợp bàn làm việc.',
                'description' => "Lưỡi hổ cỡ nhỏ, lá đứng và cứng cáp, không chiếm chỗ trên bàn.\n\n"
                    . "Chịu được ánh sáng đèn văn phòng và lịch tưới thất thường.",
                'care' => [
                    'light' => 'Sáng gián tiếp, sống được dưới đèn văn phòng',
                    'water' => 'Tưới 10 - 14 ngày/lần',
                    'soil' => 'Đất cát pha, thoát nước tốt',
                    'temperature' => '15°C - 32°C',
                    'position' => 'Bàn làm việc, kệ sách',
                    'difficulty' => 'easy',
                    'notes' => 'Mùa đông giảm tưới còn một nửa.',
                ],
                'variants' => [
                    ['name' => 'Chậu sứ trắng', 'price' => 180000, 'track' => true, 'stock' => 18],
                    ['name' => 'Chậu gốm nâu', 'price' => 195000, 'track' => true, 'stock' => 12],
                ],
            ],

            [
                'name' => 'Trầu bà leo cột',
                'code' => 'TB-LC-001',
                'category' => 'cay-canh',
                'type' => 'plant',
                'form' => 'pot',
                'price' => 420000,
                'track' => true,
                'stock' => 14,
                'short' => 'Trầu bà bám cột xơ dừa, tán rủ đều.',
                'description' => "Trầu bà bám cột xơ dừa, tán phủ đều quanh cột.\n\n"
                    . "Cây leo nhanh, thích hợp đặt cạnh cửa sổ hoặc góc cầu thang.",
                'care' => [
                    'light' => 'Sáng gián tiếp, tránh nắng trưa',
                    'water' => 'Tưới 4 - 5 ngày/lần, giữ đất hơi ẩm',
                    'soil' => 'Đất trộn xơ dừa và trấu hun',
                    'fertilizer' => 'Bón lá mỗi tháng',
                    'position' => 'Cạnh cửa sổ, góc cầu thang',
                    'difficulty' => 'easy',
                    'notes' => 'Phun ẩm cột xơ dừa để rễ khí bám tốt hơn.',
                ],
            ],

            [
                'name' => 'Sen đá mix chậu đá',
                'code' => 'SD-MX-001',
                'category' => 'sen-da-xuong-rong',
                'type' => 'plant',
                'form' => 'pot',
                'price' => 220000,
                'track' => true,
                'stock' => 25,
                'short' => 'Nhiều loại sen đá trồng chung một chậu đá.',
                'description' => "Bốn đến sáu giống sen đá trồng chung trong chậu đá tự nhiên, phủ sỏi trắng.\n\n"
                    . "Giống được chọn theo hàng có sẵn nên mỗi chậu một vẻ.",
                'care' => [
                    'light' => 'Cần nắng sáng ít nhất 3 giờ mỗi ngày',
                    'water' => 'Tưới 10 - 14 ngày/lần, tưới sát gốc',
                    'soil' => 'Đất chuyên sen đá trộn đá perlite',
                    'temperature' => '15°C - 30°C',
                    'position' => 'Ban công, bệ cửa sổ hướng nắng',
                    'difficulty' => 'medium',
                    'notes' => 'Thiếu nắng cây sẽ vươn dài và mất dáng.',
                ],
                'variants' => [
                    ['name' => 'Chậu đá 12cm', 'price' => 220000, 'track' => true, 'stock' => 15],
                    ['name' => 'Chậu đá 18cm', 'price' => 330000, 'track' => true, 'stock' => 10],
                ],
            ],

            [
                'name' => 'Bonsai mai chiếu thuỷ',
                'code' => 'BS-MC-001',
                'category' => 'cay-bonsai',
                'type' => 'plant',
                'form' => 'original',
                'price' => 2800000,
                'track' => true,
                'stock' => 2,
                'short' => 'Bonsai dáng trực, gốc lũa, mỗi cây một dáng riêng.',
                'description' => "Mai chiếu thuỷ dáng trực, gốc lũa, đã tạo tán nhiều năm.\n\n"
                    . "Là cây độc bản: mỗi cây một dáng, cửa hàng gửi ảnh thực tế của đúng cây khách sẽ nhận.",
                'care' => [
                    'light' => 'Nắng trực tiếp buổi sáng',
                    'water' => 'Tưới hàng ngày vào sáng sớm',
                    'soil' => 'Đất thịt trộn cát và xỉ than',
                    'fertilizer' => 'Bón phân hữu cơ 2 tháng/lần',
                    'temperature' => '20°C - 35°C',
                    'position' => 'Sân vườn, ban công có nắng',
                    'frequency' => 'Tỉa tán 2 - 3 tháng/lần',
                    'difficulty' => 'hard',
                    'notes' => 'Cần người biết tỉa tán, không hợp người mới bắt đầu.',
                ],
            ],
        ];
    }
}
