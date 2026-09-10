<?php

namespace Tests\Feature\Cart;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Thẻ sản phẩm của hàng CÓ QUY CÁCH phải mời chọn, không gửi thẳng.
 * ============================================================
 * LỖI ĐÃ SỬA: nút "Thêm vào giỏ" ở khối "Gợi ý cho bạn" luôn báo lỗi.
 *
 * `x-product.actions` quyết định hiện nút nào dựa trên
 * `relationLoaded('variants')`:
 *
 *   đã nạp    -> biết có quy cách -> nút mở hộp chọn
 *   CHƯA nạp  -> tưởng KHÔNG có   -> nút gửi thẳng biểu mẫu
 *
 * Mà biểu mẫu gửi thẳng thì thiếu `variant_id`, và CartService từ chối:
 * "Sản phẩm này có nhiều quy cách. Vui lòng chọn quy cách trước khi mua."
 *
 * Nghĩa là: BẤT KỲ trang nào quên `->with('variants')` đều dựng ra một
 * nút chắc chắn hỏng — âm thầm, không cảnh báo lúc dựng trang.
 * RecommendationService nạp `['category', 'promotions', 'traits']` và
 * quên đúng một cái.
 *
 * Nay component tự nạp khi thiếu, nên KHÔNG trang nào phải nhớ nữa.
 * Việc nạp sẵn ở service vẫn giữ, nhưng để tránh N+1 chứ không còn để
 * tránh lỗi.
 */
class VariantCardTest extends TestCase
{
    use RefreshDatabase;

    private function hangCoQuyCach(): Product
    {
        $product = Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->price('180000.00')
            ->stock(20)
            ->create(['name' => 'Lưỡi hổ mini để bàn']);

        foreach (['Chậu sứ trắng' => '180000.00', 'Chậu gốm nâu' => '195000.00'] as $ten => $gia) {
            ProductVariant::create([
                'product_id' => $product->id,
                'name' => $ten,
                'price' => $gia,
                'is_active' => true,
                'track_inventory' => true,
                'stock_quantity' => 10,
            ]);
        }

        return $product;
    }

    #[Test]
    public function the_san_pham_KHONG_nap_san_quy_cach_van_mo_hop_chon(): void
    {
        /*
         * DỰNG TỪ MỘT ĐỐI TƯỢNG CHƯA NẠP QUAN HỆ — đúng như mọi trang
         * quên `->with('variants')` sẽ đưa vào.
         */
        $product = Product::findOrFail($this->hangCoQuyCach()->id);

        $this->assertFalse(
            $product->relationLoaded('variants'),
            'Tình huống cần kiểm là quan hệ CHƯA nạp; nạp sẵn thì bài này vô nghĩa.',
        );

        $html = Blade::render('<x-product.card :product="$product" />', compact('product'));

        $this->assertStringContainsString(
            'data-variant-choice',
            $html,
            'Thẻ sản phẩm dựng ra nút gửi thẳng — cú bấm nào cũng sẽ bị từ chối.',
        );

        $this->assertStringNotContainsString('data-add-to-cart', $html);
    }

    #[Test]
    public function goi_y_cho_ban_o_trang_chu_khong_dung_nut_hong(): void
    {
        /*
         * ĐI QUA ĐÚNG TRANG KHÁCH NHÌN THẤY.
         *
         * Bài trên kiểm component; bài này kiểm cái ghép nối — vì lỗi
         * nằm ở chỗ service nạp thiếu một quan hệ, chứ không nằm trong
         * component.
         */
        $product = $this->hangCoQuyCach();

        $html = $this->get('/')->assertOk()->getContent();

        // Sản phẩm phải thật sự xuất hiện, nếu không bài đo vào chỗ trống.
        $this->assertStringContainsString($product->name, $html);

        $viTri = strpos($html, 'data-add-to-cart="' . $product->id . '"');

        $this->assertFalse(
            $viTri,
            'Trang chủ đang hiện nút "Thêm vào giỏ" gửi thẳng cho hàng có quy cách.',
        );
    }

    #[Test]
    public function gui_thang_khong_kem_quy_cach_van_bi_tu_choi(): void
    {
        /*
         * LỚP CHẶN Ở DƯỚI KHÔNG ĐƯỢC GỠ.
         *
         * Sửa giao diện là để khách không gặp lỗi; nó KHÔNG thay được
         * phép kiểm ở CartService — ai cũng gửi được một biểu mẫu tự chế.
         * Không có lớp này thì hàng vào giỏ với giá quy cách rẻ nhất và
         * cửa hàng không biết phải gói chậu nào.
         */
        $product = $this->hangCoQuyCach();

        $this->actingAs(User::factory()->create())
            ->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1])
            ->assertSessionHas('error');

        $this->assertSame(0, \App\Models\CartItem::count());
    }

    #[Test]
    public function chon_quy_cach_roi_thi_them_duoc_binh_thuong(): void
    {
        $product = $this->hangCoQuyCach();
        $quyCach = $product->variants()->firstOrFail();

        $this->actingAs(User::factory()->create())
            ->post('/gio-hang', [
                'product_id' => $product->id,
                'variant_id' => $quyCach->id,
                'quantity' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, \App\Models\CartItem::where('product_variant_id', $quyCach->id)->count());
    }

    #[Test]
    public function hang_KHONG_co_quy_cach_van_them_thang_duoc(): void
    {
        // Vế còn lại: đừng bắt mọi sản phẩm đi qua hộp chọn quy cách.
        $product = Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->price('120000.00')
            ->stock(20)
            ->create(['name' => 'Cây không quy cách']);

        $html = Blade::render('<x-product.card :product="$product" />', compact('product'));

        $this->assertStringContainsString('data-add-to-cart', $html);
        $this->assertStringNotContainsString('data-variant-choice', $html);
    }
}
