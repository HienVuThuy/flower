<?php

namespace Database\Seeders;

use App\Enums\CategoryKind;
use App\Enums\ProductType;
use App\Enums\SellingForm;
use App\Enums\TraitType;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** TOÀN BỘ hàng phụ trợ: chậu, vật tư, đồ phủ gốc, đồ trang trí. */
class SupplyCatalogSeeder extends Seeder
{
    private const CATEGORIES = [
        'vat-tu-cham-soc' => [
            'name' => 'Vật tư & dụng cụ chăm sóc',
            'desc' => 'Đất, phân bón, thuốc, dưỡng hoa và dụng cụ làm vườn — thứ giữ cho cây sống.',
            'sort' => 90,
        ],
        'chau-va-de-lot' => [
            'name' => 'Chậu & đế lót',
            'desc' => 'Chậu trồng, đĩa hứng nước, bình cắm hoa — thứ đựng cây.',
            'sort' => 91,
        ],
        'phu-goc-tieu-canh' => [
            'name' => 'Phủ gốc & tiểu cảnh',
            'desc' => 'Đá, sỏi, rêu và cây phủ gốc — lấp khoảng đất trống trên mặt chậu.',
            'sort' => 92,
        ],
        'phu-kien' => [
            'name' => 'Phụ kiện trang trí',
            'desc' => 'Đồ treo, nơ, dây đèn trang trí cây theo dịp lễ Tết.',
            'sort' => 93,
        ],
    ];

    private function hang(): array
    {
        return [

            'vat-tu-cham-soc' => [
                ['name' => 'Đất trồng trộn sẵn 5kg', 'code' => 'VT-DAT-5KG', 'price' => 55000,
                    'short' => 'Đất thịt trộn xơ dừa và trấu hun, tơi và thoát nước tốt.',
                    'for' => ['pot', 'original']],
                ['name' => 'Phân bón NPK dạng viên tan chậm', 'code' => 'VT-NPK-VIEN', 'price' => 65000,
                    'short' => 'Rắc lên mặt đất, tan dần trong 2–3 tháng.',
                    'for' => ['pot', 'original']],
                ['name' => 'Viên đất nung lót đáy chậu 1kg', 'code' => 'VT-DAT-NUNG', 'price' => 45000,
                    'short' => 'Rải đáy chậu để nước thoát nhanh, chống úng rễ.',
                    'for' => ['pot', 'original']],
                ['name' => 'Thuốc trị nấm lá sinh học', 'code' => 'VT-TRI-NAM', 'price' => 75000,
                    'short' => 'Chai 100ml, xịt khi lá có đốm nâu hoặc phấn trắng.',
                    'for' => ['pot', 'original']],
                ['name' => 'Dung dịch dưỡng hoa tươi', 'code' => 'VT-DUONG-HOA', 'price' => 30000,
                    'short' => 'Pha vào nước cắm, giữ hoa tươi thêm 3–5 ngày.',
                    'for' => ['bouquet', 'branch', 'basket', 'box', 'arrangement']],
                ['name' => 'Bình tưới vòi dài 1.5L', 'code' => 'VT-BINH-TUOI', 'price' => 95000,
                    'short' => 'Vòi dài tưới được vào gốc mà không ướt lá.',
                    'for' => ['pot', 'original', 'set']],
                ['name' => 'Kéo cắt cành mũi cong', 'code' => 'VT-KEO-CAT', 'price' => 85000,
                    'short' => 'Lưỡi thép không gỉ, cắt vát gốc hoa và tỉa lá khô.',
                    'for' => ['pot', 'original', 'bouquet', 'basket', 'box', 'arrangement']],
            ],

            'chau-va-de-lot' => [
                ['name' => 'Chậu sứ trắng cỡ vừa', 'code' => 'CH-SU-TRANG-M', 'price' => 120000,
                    'short' => 'Chậu sứ men trắng, đường kính 18cm, có lỗ thoát nước.',
                    'for' => ['pot', 'original']],
                ['name' => 'Đĩa lót chậu chống tràn', 'code' => 'CH-DIA-LOT', 'price' => 35000,
                    'short' => 'Đĩa nhựa trong hứng nước thừa, không đọng vệt trên sàn.',
                    'for' => ['pot', 'original']],
                ['name' => 'Bình thuỷ tinh cắm hoa', 'code' => 'CH-BINH-CAM', 'price' => 145000,
                    'short' => 'Miệng loe 12cm, hợp bó hoa cỡ vừa.',
                    'for' => ['bouquet', 'branch']],
            ],

            'phu-goc-tieu-canh' => [
                ['name' => 'Đá trắng phủ mặt chậu 1kg', 'code' => 'PG-DA-TRANG', 'price' => 40000,
                    'short' => 'Đá cuội trắng cỡ 1–2cm, rải kín mặt đất cho gọn và sạch.',
                    'for' => ['pot', 'original']],
                ['name' => 'Sỏi màu trang trí 500g', 'code' => 'PG-SOI-MAU', 'price' => 35000,
                    'short' => 'Sỏi nhiều màu cỡ nhỏ, hợp chậu để bàn và chậu sen đá.',
                    'for' => ['pot', 'original']],
                ['name' => 'Rêu khô phủ gốc 100g', 'code' => 'PG-REU-KHO', 'price' => 55000,
                    'short' => 'Rêu rừng sấy khô, giữ ẩm mặt đất và che khoảng trống quanh gốc.',
                    'for' => ['pot', 'original']],
                ['name' => 'Cỏ nhung Nhật mini phủ gốc', 'code' => 'PG-CO-NHUNG', 'price' => 60000,
                    'short' => 'Thảm cỏ sống cắt sẵn, đặt lên mặt chậu là kín gốc.',
                    'for' => ['pot', 'original']],
                ['name' => 'Tượng gốm mini trang trí chậu', 'code' => 'PG-TUONG-MINI', 'price' => 45000,
                    'short' => 'Tượng gốm cao 4–6cm, đặt cạnh gốc làm tiểu cảnh.',
                    'for' => ['pot', 'original']],
            ],

            'phu-kien' => [
                ['name' => 'Bộ 6 quả cầu Giáng sinh treo cây', 'code' => 'TT-CAU-NOEL', 'price' => 65000,
                    'short' => 'Quả cầu nhựa đỏ và vàng đồng, đường kính 4cm, có móc treo sẵn.',
                    'for' => ['pot', 'original', 'set']],
                ['name' => 'Nơ ruy băng đỏ trang trí chậu', 'code' => 'TT-NO-RUY-BANG', 'price' => 25000,
                    'short' => 'Nơ vải bản 4cm, buộc quanh miệng chậu hoặc thân cây.',
                    'for' => ['pot', 'original', 'bouquet', 'box', 'basket', 'set']],
                ['name' => 'Dây đèn LED mini quấn cây', 'code' => 'TT-DAY-DEN', 'price' => 85000,
                    'short' => 'Dây 3m, 30 bóng, chạy pin — quấn quanh tán cây để bàn.',
                    'for' => ['pot', 'original', 'set']],
                ['name' => 'Bao lì xì mini treo cây ngày Tết', 'code' => 'TT-LI-XI', 'price' => 30000,
                    'short' => 'Bộ 10 bao lì xì nhỏ kèm dây treo, trang trí cành mai cành đào.',
                    'for' => ['branch', 'pot', 'original']],
                ['name' => 'Thiệp chúc mừng kèm kẹp cắm', 'code' => 'TT-THIEP-KEP', 'price' => 15000,
                    'short' => 'Thiệp trắng kèm que kẹp, cắm thẳng vào chậu hoặc bó hoa.',
                    'for' => ['pot', 'original', 'bouquet', 'box', 'basket', 'set', 'gift']],
            ],
        ];
    }

    public function run(): void
    {
        $cat = [];

        foreach (self::CATEGORIES as $slug => $spec) {
            $cat[$slug] = Category::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $spec['name'],
                    'kind' => CategoryKind::Supply,
                    'description' => $spec['desc'],
                    'is_active' => true,
                    'sort_order' => $spec['sort'],
                ],
            );
        }

        $them = 0;
        $chuyen = 0;

        foreach ($this->hang() as $slug => $items) {
            foreach ($items as $item) {
                $product = Product::withTrashed()->where('slug', Str::slug($item['name']))->first();

                if ($product) {
                    if ($product->category_id !== $cat[$slug]->id) {
                        $product->category_id = $cat[$slug]->id;
                        $product->product_code = $item['code'];
                        $product->saveQuietly();
                        $chuyen++;
                    }
                } else {
                    $product = Product::create([
                        'category_id' => $cat[$slug]->id,
                        'name' => $item['name'],
                        'slug' => Str::slug($item['name']),
                        'product_code' => $item['code'],
                        'short_description' => $item['short'],
                        'product_type' => ProductType::Other,
                        'selling_form' => SellingForm::Other,
                        'base_price' => $item['price'],
                        'status' => 'active',
                        'track_inventory' => true,
                        'stock_quantity' => 50,
                    ]);
                    $them++;
                }

                $product->syncTraits(TraitType::AccessoryFor, $item['for']);
            }
        }

        $this->command?->info(sprintf(
            'Hàng phụ trợ: %d danh mục, thêm %d món mới, chuyển %d món sang danh mục đúng.',
            count($cat),
            $them,
            $chuyen,
        ));
    }
}
