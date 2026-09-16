<?php

namespace Database\Seeders;

use App\Enums\FengShuiElement;
use App\Enums\Placement;
use App\Enums\ProductType;
use App\Enums\SellingForm;
use App\Enums\TraitType;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * DỮ LIỆU MẪU BỔ SUNG — làm đầy những danh mục đang quá mỏng.
 * ⚠️ CỬA HÀNG PHẢI RÀ LẠI TRƯỚC KHI BÁN THẬT.
 */
class ExtraCatalogSeeder extends Seeder
{
    private const PRODUCTS = [
        [
            'slug' => 'sen-da-nau-chau-su-mini',
            'name' => 'Sen đá nâu chậu sứ mini',
            'code' => 'CB-SENDA-MINI',
            'category' => 'cay-de-ban',
            'form' => SellingForm::Pot,
            'price' => 85000,
            'stock' => 40,
            'short' => 'Chậu sứ 6cm, vừa lòng bàn tay — hợp góc bàn làm việc.',
            'description' => "Sen đá nâu (Echeveria) lá xếp hình hoa thị, viền lá ngả nâu khi đủ nắng.\n\nChậu sứ trắng đường kính 6cm, có lỗ thoát nước và đĩa lót rời. Cây cao khoảng 8–10cm tính cả chậu, đặt vừa cạnh màn hình máy tính.\n\nLƯU Ý: sen đá cần nắng. Để hẳn trong phòng thiếu sáng thì cây vươn dài, lá thưa ra và mất dáng hoa thị — đó là dấu hiệu thiếu sáng chứ không phải cây hỏng.",
            'care' => [
                'light' => 'Cần nắng trực tiếp 3–4 giờ/ngày, tốt nhất là bệ cửa sổ',
                'water' => 'Tưới đẫm rồi để khô hẳn mới tưới lại, khoảng 10 ngày/lần',
                'water_days' => 10,
                'soil' => 'Đất trộn nhiều cát và perlite, thoát nước nhanh',
                'fertilizer' => 'Bón loãng 2 tháng/lần vào mùa sinh trưởng',
                'fertilizer_days' => 60,
                'temperature' => '18°C - 30°C',
                'position' => 'Bệ cửa sổ, bàn làm việc gần cửa',
                'frequency' => 'Kiểm tra đất 1 lần/tuần',
                'difficulty' => 'easy',
                'notes' => 'Không tưới vào giữa cụm lá — nước đọng ở đó gây thối nõn.',
            ],
            'placement' => [Placement::Desk, Placement::WindowSill, Placement::Office],
            'feng_shui' => [FengShuiElement::Tho],
        ],
        [
            'slug' => 'cay-luoi-ho-vang-vien-de-ban',
            'name' => 'Lưỡi hổ vàng viền để bàn',
            'code' => 'CB-LUOIHO-VV',
            'category' => 'cay-de-ban',
            'form' => SellingForm::Pot,
            'price' => 145000,
            'stock' => 25,
            'short' => 'Lá viền vàng, cao 25cm — chịu bóng và chịu quên tưới.',
            'description' => "Sansevieria trifasciata 'Laurentii' — bản lá viền vàng của lưỡi hổ thường.\n\nCao khoảng 25cm, chậu sứ trắng 10cm. Đây là một trong số ít cây sống được ở góc gần như không có nắng, nên hay được chọn cho bàn làm việc trong phòng kín.\n\nLà cây dễ nhất để bắt đầu: chịu được quên tưới cả tháng.",
            'care' => [
                'light' => 'Chịu được ánh sáng yếu, không cần nắng trực tiếp',
                'water' => 'Tưới khi đất khô hẳn, khoảng 2 tuần/lần',
                'water_days' => 14,
                'soil' => 'Đất tơi xốp, thoát nước tốt',
                'fertilizer' => 'Bón NPK loãng 3 tháng/lần',
                'fertilizer_days' => 90,
                'temperature' => '15°C - 32°C',
                'position' => 'Bàn làm việc, phòng ngủ, góc phòng ít sáng',
                'frequency' => 'Kiểm tra 2 tuần/lần',
                'difficulty' => 'easy',
                'notes' => 'Chết vì úng nhiều hơn vì khô. Thà quên tưới còn hơn tưới thừa.',
            ],
            'placement' => [Placement::Desk, Placement::Office, Placement::Bedroom, Placement::Hallway],
            'feng_shui' => [FengShuiElement::Kim, FengShuiElement::Tho],
        ],

        [
            'slug' => 'bonsai-tung-la-han-dang-truc',
            'name' => 'Bonsai tùng la hán dáng trực',
            'code' => 'BS-TUNGLAHAN',
            'category' => 'cay-bonsai',
            'form' => SellingForm::Original,
            'price' => 2850000,
            'stock' => 3,
            'short' => 'Cao 45cm, gốc 4cm — dáng trực cổ, mỗi cây một thế riêng.',
            'description' => "Tùng la hán (Podocarpus macrophyllus) dáng trực, cao khoảng 45cm tính cả chậu, đường kính gốc khoảng 4cm.\n\nCây đã được tạo dáng và nuôi trong chậu nhiều năm, tán phân tầng rõ. MỖI CÂY MỘT THẾ RIÊNG — ảnh chỉ mang tính minh hoạ, cửa hàng gửi ảnh thật của cây bạn chọn trước khi giao.\n\nĐây là cây cho người đã có kinh nghiệm: tùng la hán cần nắng, cần tỉa định kỳ và không tha thứ cho việc tưới bừa.",
            'care' => [
                'light' => 'Nắng trực tiếp buổi sáng, che bớt nắng gắt buổi trưa',
                'water' => 'Tưới khi mặt đất se, giữ ẩm đều nhưng không sũng',
                'water_days' => 3,
                'soil' => 'Đất bonsai trộn akadama và đá pumice',
                'fertilizer' => 'Bón hữu cơ dạng viên, thay 1 tháng/lần',
                'fertilizer_days' => 30,
                'temperature' => '18°C - 33°C',
                'position' => 'Ban công, sân vườn có nắng sáng',
                'frequency' => 'Kiểm tra hằng ngày, tỉa 2 tháng/lần',
                'difficulty' => 'hard',
                'notes' => 'Không để trong nhà quá 3 ngày liên tục — cây cần nắng thật để giữ tán.',
            ],
            'placement' => [Placement::Balcony, Placement::Garden],
            'feng_shui' => [FengShuiElement::Moc],
        ],

        [
            'slug' => 'hoa-cai-ao-chu-re',
            'name' => 'Hoa cài áo chú rể',
            'code' => 'HC-CAIAO',
            'category' => 'hoa-cuoi',
            'price' => 120000,
            'form' => SellingForm::Other,
            'stock' => null,
            'short' => 'Ghim cài ve áo, làm đồng bộ với hoa cầm tay cô dâu.',
            'description' => "Hoa cài áo (boutonnière) cho chú rể và phù rể, làm theo đúng tông hoa cầm tay của cô dâu.\n\nCó ghim cài an toàn phía sau, không xuyên qua mặt vải. Đặt kèm hoa cầm tay thì cửa hàng dùng chung một mẻ hoa nên màu khớp tuyệt đối.\n\nLÀM THEO YÊU CẦU: báo trước tối thiểu 2 ngày.",
            'care' => [
                'water_change' => 'Không cắm nước — giữ trong hộp mát tới giờ dùng',
                'lifespan' => 'Tươi trong ngày, đẹp nhất trong 8 giờ đầu',
                'notes' => 'Để ngăn mát tủ lạnh (không phải ngăn đá) nếu nhận trước.',
            ],
        ],
        [
            'slug' => 'hoa-de-ban-tiec-cuoi',
            'name' => 'Hoa để bàn tiệc cưới',
            'code' => 'HC-DEBAN',
            'category' => 'hoa-cuoi',
            'price' => 450000,
            'form' => SellingForm::Arrangement,
            'stock' => null,
            'short' => 'Lọ hoa thấp cho bàn tiệc — không che mặt người đối diện.',
            'description' => "Bình hoa thấp đặt giữa bàn tiệc, cao dưới 30cm.\n\nCHIỀU CAO LÀ CHỦ Ý: hoa để bàn tiệc cao quá thì khách hai bên bàn không nhìn thấy nhau, và đó là lỗi thường gặp nhất khi tự cắm. Dáng thấp toả rộng giải quyết đúng chuyện đó.\n\nGiá tính cho một bàn. Đặt từ 10 bàn trở lên xin gửi yêu cầu qua trang Sự kiện & số lượng lớn để cửa hàng báo giá theo bộ.",
            'care' => [
                'water_change' => 'Thay nước mỗi ngày nếu tiệc kéo dài',
                'trim' => 'Không cần cắt lại — hoa đã cắm cố định trong xốp giữ ẩm',
                'lifespan' => 'Tươi 2–3 ngày',
                'notes' => 'Đặt xa lối đi và xa nến để tránh va đổ.',
            ],
        ],

        [
            'slug' => 'xuong-rong-bi-chau-dat-nung',
            'name' => 'Xương rồng bi chậu đất nung',
            'code' => 'SD-XRBI',
            'category' => 'sen-da-xuong-rong',
            'form' => SellingForm::Pot,
            'price' => 95000,
            'stock' => 35,
            'short' => 'Chậu đất nung 8cm, gai ngắn — an toàn hơn khi có trẻ nhỏ.',
            'description' => "Xương rồng bi (Echinopsis) thân tròn, gai ngắn và mềm hơn hẳn các loại xương rồng cột.\n\nChậu đất nung 8cm — đất nung hút ẩm nên rễ thoáng hơn chậu sứ, hợp với cây chịu hạn.\n\nCây trưởng thành ra hoa lớn màu hồng nhạt, thường nở về đêm và tàn sau một ngày.",
            'care' => [
                'light' => 'Càng nhiều nắng càng tốt, tối thiểu 4 giờ/ngày',
                'water' => 'Mùa nóng 2 tuần/lần, mùa lạnh gần như không tưới',
                'water_days' => 14,
                'soil' => 'Đất chuyên xương rồng, trộn nhiều cát thô',
                'fertilizer' => 'Bón loãng 3 tháng/lần',
                'fertilizer_days' => 90,
                'temperature' => '20°C - 38°C',
                'position' => 'Ban công, bệ cửa sổ hướng nam',
                'frequency' => 'Kiểm tra 2 tuần/lần',
                'difficulty' => 'easy',
                'notes' => 'Thân mềm và ngả vàng là dấu hiệu thừa nước, không phải thiếu.',
            ],
            'placement' => [Placement::Balcony, Placement::WindowSill, Placement::Desk],
            'feng_shui' => [FengShuiElement::Tho],
        ],
        [
            'slug' => 'sen-da-kim-cuong-chau-treo',
            'name' => 'Sen đá kim cương chậu treo',
            'code' => 'SD-KIMCUONG',
            'category' => 'sen-da-xuong-rong',
            'form' => SellingForm::Pot,
            'price' => 175000,
            'stock' => 18,
            'short' => 'Chậu treo dây gai, lá phủ phấn trắng ánh bạc.',
            'description' => "Sen đá kim cương (Graptopetalum) lá phủ lớp phấn trắng ánh bạc, xếp thành hoa thị đường kính 8–10cm.\n\nChậu treo bằng dây gai, treo được ở ban công hoặc cạnh cửa sổ. Lớp phấn trên lá là lớp bảo vệ tự nhiên — CHẠM TAY VÀO SẼ MẤT và không mọc lại ở lá đó.\n\nGiao kèm móc treo.",
            'care' => [
                'light' => 'Nắng gián tiếp mạnh; nắng gắt cả ngày làm cháy mép lá',
                'water' => 'Tưới quanh mép chậu, tránh dội lên lá, 10 ngày/lần',
                'water_days' => 10,
                'soil' => 'Đất thoát nước nhanh, trộn perlite',
                'fertilizer' => 'Bón loãng 2 tháng/lần',
                'fertilizer_days' => 60,
                'temperature' => '18°C - 30°C',
                'position' => 'Ban công có mái, cạnh cửa sổ',
                'frequency' => 'Kiểm tra 1 tuần/lần',
                'difficulty' => 'medium',
                'notes' => 'Không chạm tay lên lá — lớp phấn trắng mất là mất vĩnh viễn.',
            ],
            'placement' => [Placement::Balcony, Placement::WindowSill],
            'feng_shui' => [FengShuiElement::Kim],
        ],

        [
            'slug' => 'hop-hoa-tulip-vang',
            'name' => 'Hộp hoa tulip vàng',
            'code' => 'HQ-TULIP-VANG',
            'category' => 'hoa-qua-tang',
            'form' => SellingForm::Box,
            'price' => 590000,
            'stock' => 12,
            'short' => 'Hộp giấy cứng có xốp giữ ẩm — người nhận không cần bình cắm.',
            'description' => "12 bông tulip vàng cắm trong hộp giấy cứng, đáy lót xốp giữ ẩm.\n\nHỘP THAY CHO BÌNH CẮM: người nhận đặt luôn lên bàn, không phải đi tìm bình. Đây là điểm khác quan trọng so với bó hoa — hợp khi tặng người ở ký túc xá, văn phòng, hoặc đang đi công tác.\n\nKèm thiệp viết tay theo lời nhắn bạn ghi ở bước thanh toán.",
            'care' => [
                'water_change' => 'Rót thêm 2–3 thìa nước vào xốp mỗi ngày',
                'trim' => 'Không cần cắt gốc — hoa đã cố định trong xốp',
                'lifespan' => 'Tươi 4–6 ngày nếu để nơi mát',
                'notes' => 'Tránh đặt cạnh cửa sổ nắng gắt hoặc ngay dưới điều hoà.',
            ],
        ],
        [
            'slug' => 'gio-hoa-huong-duong-mini',
            'name' => 'Giỏ hoa hướng dương mini',
            'code' => 'HQ-GIO-HD',
            'category' => 'hoa-qua-tang',
            'form' => SellingForm::Basket,
            'price' => 420000,
            'stock' => 15,
            'short' => 'Giỏ mây có quai, 5 bông hướng dương — cầm đi tặng được ngay.',
            'description' => "5 bông hướng dương cùng lá phụ, cắm trong giỏ mây đan tay có quai xách.\n\nGiỏ đứng vững, có quai nên cầm đi tặng thuận tay và người nhận không phải loay hoay tìm chỗ cắm.\n\nHướng dương là hoa của lời chúc mừng và động viên — hợp dịp khai trương nhỏ, thi cử, hoặc thăm người mới ốm dậy.",
            'care' => [
                'water_change' => 'Thêm nước vào xốp giỏ mỗi ngày',
                'trim' => 'Nhặt bỏ lá vàng ở gốc cành',
                'lifespan' => 'Tươi 5–7 ngày',
                'notes' => 'Hướng dương uống nhiều nước — kiểm tra xốp mỗi sáng.',
            ],
        ],

        [
            'slug' => 'ke-hoa-khai-truong-hai-tang',
            'name' => 'Kệ hoa khai trương hai tầng',
            'code' => 'SK-KE-2T',
            'category' => 'hoa-khai-truong-su-kien',
            'form' => SellingForm::Arrangement,
            'price' => 1650000,
            'stock' => null,
            'short' => 'Cao 1m8, hai tầng hoa, kèm băng rôn in theo yêu cầu.',
            'description' => "Kệ hoa hai tầng cao khoảng 1m8, dùng cho khai trương cửa hàng mặt phố.\n\nHoa chủ đạo là lay ơn, cúc mâm xôi và lan hồ điệp giả trang trí. Băng rôn in theo nội dung bạn cung cấp — CỬA HÀNG IN ĐÚNG NHƯ BẠN GÕ, nên xin kiểm tra kỹ chính tả tên công ty trước khi gửi.\n\nLÀM THEO YÊU CẦU: báo trước tối thiểu 1 ngày; đặt từ 5 kệ trở lên xin báo trước 3 ngày.",
            'care' => [
                'water_change' => 'Xịt ẩm mặt hoa buổi sáng nếu trưng ngoài trời',
                'lifespan' => 'Giữ dáng 3–5 ngày tuỳ thời tiết',
                'placement' => 'Đặt tránh gió lùa và nắng chiếu thẳng',
                'notes' => 'Kệ cao và nhẹ — buộc cố định nếu đặt nơi nhiều gió.',
            ],
        ],

        [
            'slug' => 'bo-cuc-hoa-mi-trang',
            'name' => 'Bó cúc hoạ mi trắng',
            'code' => 'HO-CUCHOAMI',
            'category' => 'hoa',
            'form' => SellingForm::Bouquet,
            'price' => 260000,
            'stock' => 20,
            'short' => 'Hoa theo mùa cuối thu — bó giấy kraft, mộc mạc.',
            'description' => "Cúc hoạ mi trắng nhuỵ vàng, bó giấy kraft buộc dây đay.\n\nHOA THEO MÙA: cúc hoạ mi chỉ có khoảng 3–4 tuần cuối thu mỗi năm. Ngoài mùa cửa hàng không nhận đặt, và cũng không thay bằng loại khác rồi gọi tên này.\n\nCành mảnh, hoa nhỏ — hợp bình thuỷ tinh miệng hẹp hơn là bình to.",
            'care' => [
                'water_change' => 'Thay nước mỗi ngày, cắm ngập 1/3 cành',
                'trim' => 'Cắt vát gốc 2 ngày/lần',
                'lifespan' => 'Tươi 4–5 ngày',
                'notes' => 'Tuốt hết lá phần ngập nước — lá úng làm nước đục rất nhanh.',
            ],
        ],
        [
            'slug' => 'bo-hoa-mau-don-do',
            'name' => 'Bó hoa mẫu đơn đỏ',
            'code' => 'HO-MAUDON',
            'category' => 'hoa',
            'form' => SellingForm::Bouquet,
            'price' => 890000,
            'stock' => 8,
            'short' => 'Mẫu đơn nhập khẩu, nụ nở dần trong 2–3 ngày.',
            'description' => "7 cành mẫu đơn đỏ nhập khẩu, bó tròn kèm lá bạch đàn.\n\nGIAO KHI CÒN NỤ, và đó là cách đúng: mẫu đơn nở dần trong 2–3 ngày sau khi cắm, và bó nở sẵn thì chỉ đẹp được một hôm. Nhận về thấy nụ chưa bung là bình thường, không phải hoa non.\n\nMẫu đơn là hoa cao cấp, số lượng theo mẻ nhập — hết mẻ thì phải chờ đợt sau.",
            'care' => [
                'water_change' => 'Thay nước mỗi ngày, nước lạnh giúp nụ bung chậm và đều',
                'trim' => 'Cắt vát gốc dưới vòi nước đang chảy',
                'lifespan' => 'Tươi 5–7 ngày kể từ khi nụ bắt đầu bung',
                'notes' => 'Để nơi mát thì nụ nở chậm; muốn nở nhanh hơn thì đưa ra chỗ ấm.',
            ],
        ],

        [
            'slug' => 'cay-lan-y-chau-su-trang',
            'name' => 'Cây lan ý chậu sứ trắng',
            'code' => 'CX-LANY',
            'category' => 'cay-canh',
            'form' => SellingForm::Pot,
            'price' => 235000,
            'stock' => 22,
            'short' => 'Hoa trắng dạng mo, chịu bóng — hợp phòng tắm và góc tối.',
            'description' => "Lan ý (Spathiphyllum) lá xanh đậm bóng, hoa trắng dạng mo vươn cao trên tán.\n\nChịu bóng tốt và ưa ẩm, nên đây là một trong ít cây có hoa sống được ở phòng tắm có cửa sổ nhỏ.\n\nDẤU HIỆU DỄ ĐỌC: lan ý rũ lá khi thiếu nước và dựng lại trong vài giờ sau khi tưới — cây tự báo cho bạn biết, rất hợp người mới.",
            'care' => [
                'light' => 'Sáng gián tiếp; tránh nắng trực tiếp làm cháy mép lá',
                'water' => 'Tưới khi lá bắt đầu rũ nhẹ, khoảng 5 ngày/lần',
                'water_days' => 5,
                'soil' => 'Đất giữ ẩm nhưng vẫn thoát nước',
                'fertilizer' => 'Bón NPK loãng 1 tháng/lần để ra hoa đều',
                'fertilizer_days' => 30,
                'temperature' => '18°C - 30°C',
                'position' => 'Phòng tắm có cửa sổ, phòng khách, hành lang',
                'frequency' => 'Kiểm tra 3–4 ngày/lần',
                'difficulty' => 'easy',
                'notes' => 'Nhựa cây gây ngứa nếu nuốt phải — để xa trẻ nhỏ và thú nuôi.',
            ],
            'placement' => [Placement::Bathroom, Placement::LivingRoom, Placement::Hallway, Placement::Office],
            'feng_shui' => [FengShuiElement::Kim, FengShuiElement::Thuy],
        ],
    ];

    public function run(): void
    {
        $created = 0;
        $skipped = 0;

        foreach (self::PRODUCTS as $spec) {
            $category = Category::where('slug', $spec['category'])->first();

            if (! $category) {
                $this->command?->warn("Bỏ qua '{$spec['slug']}': không có danh mục '{$spec['category']}'.");
                $skipped++;

                continue;
            }

            $tracks = $spec['stock'] !== null;

            $product = Product::updateOrCreate(
                ['slug' => $spec['slug']],
                [
                    'category_id' => $category->id,
                    'name' => $spec['name'],
                    'product_code' => $spec['code'],
                    'short_description' => $spec['short'],
                    'description' => $spec['description'],
                    'product_type' => $category->kind === \App\Enums\CategoryKind::Supply
                        ? ProductType::Other
                        : ($spec['form'] === SellingForm::Pot || $spec['form'] === SellingForm::Original
                            ? ProductType::Plant
                            : ProductType::Flower),
                    'selling_form' => $spec['form'],
                    'base_price' => $spec['price'],
                    'care_info' => $spec['care'],
                    'status' => 'active',
                    'track_inventory' => $tracks,
                    'stock_quantity' => $spec['stock'] ?? 0,
                ],
            );

            $product->syncTraits(
                TraitType::Placement,
                array_map(fn (Placement $p) => $p->value, $spec['placement'] ?? []),
            );

            $product->syncTraits(
                TraitType::FengShui,
                array_map(fn (FengShuiElement $e) => $e->value, $spec['feng_shui'] ?? []),
            );

            $created++;
        }

        $this->command?->info("Đã tạo/cập nhật {$created} sản phẩm mẫu, bỏ qua {$skipped}.");
        $this->command?->line('Tải ảnh: node tools/fetch-product-photos.mjs');
    }
}
