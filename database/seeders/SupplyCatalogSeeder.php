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

/**
 * TOÀN BỘ hàng phụ trợ: chậu, vật tư, đồ phủ gốc, đồ trang trí.
 * ============================================================
 * NƠI DUY NHẤT khai nhóm hàng không phải cây. Trước đây phần này nằm lẫn
 * trong PlantAdvisorSeeder — một seeder tên là "tư vấn chọn cây" mà lại
 * quyết định cửa hàng có những danh mục phụ kiện nào. Ai đi tìm "vì sao
 * chậu sứ nằm ở danh mục này" sẽ không nghĩ tới việc mở tệp đó ra.
 *
 * ============================================================
 * VÌ SAO CHIA LẠI THÀNH BỐN NHÓM.
 *
 * Cũ chỉ có hai: "Phụ kiện" và "Vật tư chăm sóc", và ranh giới giữa
 * chúng là BỀN hay TIÊU HAO. Ranh giới đó sai với cách khách đi mua:
 *
 *   - Bình tưới và kéo cắt cành nằm ở "Phụ kiện" cùng với chậu sứ, dù
 *     chúng là DỤNG CỤ CHĂM CÂY chứ không phải đồ đi kèm chậu.
 *   - Người mua cây về trồng thì tìm đá, sỏi, rêu để phủ mặt chậu cho
 *     đỡ trống — cửa hàng KHÔNG có nhóm nào cho thứ đó.
 *   - Người mua cây làm quà dịp Noel hay Tết thì tìm quả cầu, nơ, đồ
 *     treo — cũng không có nhóm nào.
 *
 * Bốn nhóm mới chia theo VIỆC KHÁCH ĐANG LÀM, không theo tuổi thọ món
 * hàng:
 *
 *   | Nhóm                  | Khách đang làm gì                    |
 *   |-----------------------|--------------------------------------|
 *   | Vật tư chăm sóc       | nuôi cây sống                        |
 *   | Chậu & đế lót         | đựng cây                             |
 *   | Phủ gốc & tiểu cảnh   | làm mặt chậu đẹp lên                 |
 *   | Phụ kiện trang trí    | trang trí theo dịp                   |
 *
 * "Phụ kiện" GIỮ NGUYÊN SLUG `phu-kien` nhưng đổi nghĩa thành đồ trang
 * trí — đúng nghĩa mà người Việt hiểu khi nghe "phụ kiện". Đổi slug thì
 * mọi liên kết đã chia sẻ đều gãy, mà cái tên thì vẫn hợp.
 */
class SupplyCatalogSeeder extends Seeder
{
    /**
     * Bốn danh mục hàng phụ trợ.
     *
     * `sort_order` từ 90 trở lên: khách vào cửa hàng hoa để tìm hoa
     * trước, mấy nhóm này xếp cuối trang Danh mục.
     *
     * @var array<string, array{name: string, desc: string, sort: int}>
     */
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

    /**
     * Hàng phụ trợ, gom theo danh mục.
     *
     * `for` = hình thức bán mà món này dùng kèm — nguồn của gợi ý "mua
     * kèm". Xem PlantAdvisor::accessoriesFor(): phụ kiện tự khai mình
     * hợp với loại hàng nào, nhờ vậy cây nhập về sau này cũng tự có gợi
     * ý mà không ai phải nối tay từng cặp.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function hang(): array
    {
        return [

            /* ============================================================
             * VẬT TƯ & DỤNG CỤ — nuôi cây sống
             * ============================================================
             * Bình tưới và kéo cắt cành CHUYỂN TỪ "Phụ kiện" SANG ĐÂY.
             * Chúng là dụng cụ chăm cây, không phải đồ đi kèm chậu — và
             * người đi tìm bình tưới sẽ tìm ở nhóm chăm sóc.
             */
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

            /* ============================================================
             * CHẬU & ĐẾ LÓT — đựng cây
             * ============================================================ */
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

            /* ============================================================
             * PHỦ GỐC & TIỂU CẢNH — nhóm MỚI
             * ============================================================
             * Mua một chậu cây về thì mặt đất trơ ra một khoảng nâu, và
             * gần như ai cũng đi tìm thứ lấp nó: đá trắng, sỏi màu, rêu,
             * hoặc một loại cỏ nhỏ phủ kín gốc.
             *
             * Trước đây cửa hàng không có nhóm nào cho việc đó, nên
             * khách mua cây xong phải đi chỗ khác — và món kèm thì bao
             * giờ cũng dễ bán hơn món chính.
             *
             * `for` chỉ gồm 'pot' và 'original': đá phủ gốc không dùng
             * được cho một bó hoa.
             */
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

            /* ============================================================
             * PHỤ KIỆN TRANG TRÍ — nhóm được ĐỔI NGHĨA
             * ============================================================
             * "Phụ kiện" cũ chứa chậu, đĩa lót, bình tưới, kéo — toàn đồ
             * dùng. Nhưng khi người Việt nói "phụ kiện" cho một chậu cây
             * thì họ nghĩ tới đồ trang trí: quả cầu Giáng sinh, nơ, đồ
             * treo ngày Tết.
             *
             * Danh mục giữ nguyên slug, đổi tên và đổi hàng bên trong.
             *
             * `status` để 'active' quanh năm — cửa hàng bán trước dịp
             * chứ không đợi tới đúng ngày. Muốn ẩn ngoài mùa thì đó là
             * quyết định của admin ở trang quản trị, không phải thứ
             * seeder áp đặt.
             */
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
            /*
             * updateOrCreate chứ không firstOrCreate: "Phụ kiện" đã tồn
             * tại và cần ĐỔI TÊN thành "Phụ kiện trang trí". firstOrCreate
             * sẽ bỏ qua và cửa hàng giữ nguyên cái tên gây hiểu nhầm.
             */
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
                    /*
                     * ĐÃ CÓ: chỉ sửa danh mục và mã, KHÔNG đụng giá hay
                     * mô tả. Admin có thể đã chỉnh giá ở trang quản trị,
                     * và một lần chạy seeder không được phép cuốn mất
                     * việc đó.
                     */
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
                        /*
                         * Hình thức bán 'other'.
                         *
                         * QUAN TRỌNG: gán 'pot' thì chậu sứ rỗng sẽ tự
                         * gợi ý chính nó làm phụ kiện cho nó —
                         * accessoriesFor() tra theo hình thức bán của
                         * sản phẩm đang xem.
                         */
                        'selling_form' => SellingForm::Other,
                        'base_price' => $item['price'],
                        'status' => 'active',
                        'track_inventory' => true,
                        'stock_quantity' => 50,
                    ]);
                    $them++;
                }

                // Nhãn "dùng kèm loại hàng nào" — nguồn của gợi ý mua kèm.
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
