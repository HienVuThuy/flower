<?php

namespace Tests\Feature\Cart;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Thêm vào giỏ — hai dạng trả lời, MỘT luồng xử lý.
 * ============================================================
 * Trình duyệt gửi biểu mẫu bình thường thì nhận chuyển hướng; JavaScript
 * gọi bằng fetch thì nhận JSON. Điều được canh chừng ở đây là HAI DẠNG
 * ĐÓ KHÔNG ĐƯỢC KHÁC NHAU VỀ LUẬT — nếu nhánh JSON lỏng hơn một chút
 * thôi thì nó thành đường vòng qua mọi phép kiểm tra.
 */
class AddToCartTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $state = []): Product
    {
        return Product::factory()->for(Category::factory())->create($state);
    }

    /** Header mà add-to-cart.js gửi kèm. */
    private function ajax(): array
    {
        return ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];
    }

    // ================= KHÔNG CÓ JAVASCRIPT =================

    #[Test]
    public function gui_bieu_mau_binh_thuong_thi_van_chuyen_huong_nhu_cu(): void
    {
        // Đây là đường đi CHÍNH, không phải đường dự phòng. JavaScript
        // hỏng hay chưa tải xong thì khách vẫn phải mua được hàng.
        $product = $this->product();

        $this->from('/san-pham')
            ->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1])
            ->assertRedirect('/san-pham')
            ->assertSessionHas('success');
    }

    #[Test]
    public function gui_bieu_mau_binh_thuong_gap_loi_thi_bao_bang_flash(): void
    {
        $this->from('/san-pham')
            ->post('/gio-hang', ['product_id' => 999999, 'quantity' => 1])
            ->assertRedirect('/san-pham')
            ->assertSessionHasErrors('product_id');
    }

    // ================= CÓ JAVASCRIPT =================

    #[Test]
    public function goi_bang_fetch_thi_nhan_json_kem_so_mon_trong_gio(): void
    {
        $product = $this->product();

        $this->withHeaders($this->ajax())
            ->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk()
            ->assertJson(['ok' => true, 'cartCount' => 2])
            ->assertJsonStructure(['ok', 'message', 'cartCount']);
    }

    #[Test]
    public function so_mon_trong_gio_do_may_chu_dem_chu_khong_phai_trinh_duyet_tu_cong(): void
    {
        // Tự cộng ở trình duyệt là sai ngay khi giỏ GỘP dòng trùng thay
        // vì thêm dòng mới — hai lần thêm 1 món ra 2, không phải 2 dòng.
        //
        // Dùng tài khoản ĐÃ ĐĂNG NHẬP: giỏ khách vãng lai nhận diện bằng
        // session()->getId(), mà TestCase của Laravel cấp phiên mới cho
        // mỗi request nên hai lần gọi sẽ ra hai giỏ khác nhau. Đó là
        // giới hạn của công cụ kiểm thử, không phải của ứng dụng — xem
        // docs/KIEM-THU.md.
        $this->actingAs(User::factory()->create());
        $product = $this->product();

        $this->withHeaders($this->ajax())
            ->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1])
            ->assertJson(['cartCount' => 1]);

        $this->withHeaders($this->ajax())
            ->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1])
            ->assertJson(['cartCount' => 2]);

        $this->assertSame(1, \App\Models\CartItem::count(), 'Phải gộp dòng, không thêm dòng mới.');
    }

    #[Test]
    public function hang_khong_ban_truc_tiep_bi_tu_choi_o_ca_hai_duong(): void
    {
        // Sản phẩm chỉ nhận báo giá. Nhánh JSON mà lỏng hơn thì nó thành
        // đường vòng qua đúng phép kiểm tra này.
        // base_price = null nghĩa là "liên hệ báo giá" — xem
        // App\Services\Pricing\ProductPrice::isContactForPrice().
        $baoGia = $this->product(['base_price' => null]);

        $this->withHeaders($this->ajax())
            ->post('/gio-hang', ['product_id' => $baoGia->id, 'quantity' => 1])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        /*
         * flushHeaders() — BẮT BUỘC.
         *
         * withHeaders() gắn header vào cả TestCase, không phải chỉ một
         * request. Không xoá thì request dưới vẫn mang Accept:
         * application/json và ta tưởng mình đang thử đường không có
         * JavaScript, trong khi thực ra thử lại đúng đường vừa thử.
         */
        $this->flushHeaders();

        $this->from('/san-pham')
            ->post('/gio-hang', ['product_id' => $baoGia->id, 'quantity' => 1])
            ->assertRedirect('/san-pham')
            ->assertSessionHas('error');

        $this->assertSame(0, \App\Models\CartItem::count());
    }

    #[Test]
    public function du_lieu_sai_tra_ve_422_chu_khong_phai_200(): void
    {
        // Trả 200 kèm ok=false thì mọi công cụ theo dõi đều thấy một
        // request thành công, và lỗi thật biến mất khỏi biểu đồ.
        $this->withHeaders($this->ajax())
            ->post('/gio-hang', ['product_id' => 999999, 'quantity' => 1])
            ->assertStatus(422);

        $this->withHeaders($this->ajax())
            ->post('/gio-hang', ['product_id' => $this->product()->id, 'quantity' => 0])
            ->assertStatus(422);
    }

    #[Test]
    public function khach_vang_lai_cung_them_duoc_vao_gio(): void
    {
        $product = $this->product();

        $this->withHeaders($this->ajax())
            ->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    #[Test]
    public function khach_da_dang_nhap_them_vao_dung_gio_cua_minh(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->product();

        $this->withHeaders($this->ajax())
            ->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 3])
            ->assertJson(['cartCount' => 3]);

        $this->assertSame($user->id, \App\Models\Cart::first()->user_id);
    }

    // ================= "MUA NGAY" KHÔNG BỊ ẢNH HƯỞNG =================

    #[Test]
    public function mua_ngay_van_chuyen_sang_trang_thanh_toan(): void
    {
        // "Mua ngay" nằm chung biểu mẫu với "Thêm vào giỏ" nhưng nó CỐ Ý
        // rời trang. add-to-cart.js chỉ chặn nút mang data-add-to-cart.
        $product = $this->product();

        $this->post('/mua-ngay', ['product_id' => $product->id, 'quantity' => 1])
            ->assertRedirect('/thanh-toan');

        $this->assertSame(0, \App\Models\CartItem::count(), 'Mua ngay không được đụng vào giỏ.');
    }

    // ================= GIAO DIỆN CÓ ĐỦ CHỖ BÁM =================

    #[Test]
    public function trang_chu_co_du_thuoc_tinh_cho_javascript_bam_vao(): void
    {
        /*
         * Bài kiểm tra này DÒ CHUỖI trong HTML, khác nguyên tắc chung.
         *
         * Lý do: mấy thuộc tính này là HỢP ĐỒNG giữa Blade và
         * add-to-cart.js. Đổi tên một cái ở đây thì nút im lặng quay về
         * tải lại trang — không lỗi, không cảnh báo, chỉ là tính năng
         * biến mất mà không ai biết.
         */
        $product = $this->product();

        $html = $this->get('/')->assertOk()->getContent();

        /*
         * KHỚP CHÍNH XÁC, không phải "có chứa chuỗi".
         *
         * assertSee('data-add-to-cart') vẫn xanh khi ai đó đổi thuộc
         * tính thành data-add-to-cart-cu — đã thử và nó thật sự xanh.
         * Một bài kiểm tra xanh trong trường hợp hỏng còn tệ hơn không
         * có bài nào, vì nó khiến người ta yên tâm.
         */
        $this->assertMatchesRegularExpression(
            '/class="product-buy"/',
            $html,
            'Biểu mẫu mua hàng phải mang đúng lớp product-buy.',
        );

        $this->assertMatchesRegularExpression(
            '/data-add-to-cart="'.$product->id.'"/',
            $html,
            'Nút "Thêm vào giỏ" phải mang đúng thuộc tính data-add-to-cart.',
        );

        $this->assertMatchesRegularExpression(
            '/data-cart-badge[\s>]/',
            $html,
            'Huy hiệu số món phải có chỗ cho JavaScript bám vào.',
        );

        $this->assertMatchesRegularExpression(
            '/data-cart-link[\s>]/',
            $html,
            'Liên kết giỏ hàng phải có chỗ cho JavaScript bám vào.',
        );
    }
}
