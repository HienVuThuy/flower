<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tìm kiếm và bộ lọc ở các trang danh sách của quản trị.
 * ============================================================
 * BỘ LỌC SAI KHÔNG BAO GIỜ BÁO LỖI. Nó chỉ trả về ít kết quả hơn — hoặc
 * nhiều hơn — và admin tin vào con số đó. Một ô lọc "hết hàng" gộp nhầm
 * cả hàng làm theo đơn thì mỗi lần nhập hàng lại nhập thừa.
 *
 * Vì thế mỗi bài ở đây dựng dữ liệu có CẢ thứ phải khớp lẫn thứ KHÔNG
 * được khớp, rồi đếm.
 */
class ListFilterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin());
    }

    // ================= SẢN PHẨM =================

    #[Test]
    public function tim_san_pham_theo_ten_va_theo_ma(): void
    {
        $cat = Category::factory()->create();
        Product::factory()->for($cat)->create(['name' => 'Hộp hoa tulip vàng', 'product_code' => 'TLP01']);
        Product::factory()->for($cat)->create(['name' => 'Sen đá nâu', 'product_code' => 'SND02']);

        $this->get('/admin/products?q=tulip')
            ->assertOk()
            ->assertSee('Hộp hoa tulip vàng')
            ->assertDontSee('Sen đá nâu');

        // Tìm được cả bằng mã — admin đọc mã trên đơn hàng.
        $this->get('/admin/products?q=SND02')
            ->assertSee('Sen đá nâu')
            ->assertDontSee('Hộp hoa tulip vàng');
    }

    #[Test]
    public function tim_duoc_cum_o_giua_ten_chu_khong_chi_dau_ten(): void
    {
        // Khách gọi tới nói "tulip" chứ không đọc cả "Hộp hoa tulip vàng".
        // Chỉ khớp đầu chuỗi thì gần như không tìm ra gì.
        $cat = Category::factory()->create();
        Product::factory()->for($cat)->create(['name' => 'Hộp hoa tulip vàng']);

        $this->get('/admin/products?q=tulip')->assertSee('Hộp hoa tulip vàng');
    }

    #[Test]
    public function loc_het_hang_KHONG_gom_hang_lam_theo_don(): void
    {
        /*
         * ĐÂY LÀ CHỖ DỄ SAI NHẤT.
         *
         * Hàng làm theo đơn (hoa cưới, hoa sự kiện) có track_inventory =
         * false và stock_quantity = 0 — nhưng nó KHÔNG hết hàng, cửa hàng
         * làm khi có đơn. Gộp chung thì mỗi lần lọc "hết hàng" lại thấy
         * toàn hoa cưới, và admin đi nhập thứ không cần nhập.
         */
        $cat = Category::factory()->create();
        Product::factory()->for($cat)->stock(0)->create(['name' => 'Chậu sứ đã hết']);
        Product::factory()->for($cat)->madeToOrder()->create(['name' => 'Hoa cưới cầm tay']);

        $this->get('/admin/products?kho=het')
            ->assertOk()
            ->assertSee('Chậu sứ đã hết')
            ->assertDontSee('Hoa cưới cầm tay');
    }

    #[Test]
    public function loc_sap_het_chi_lay_hang_con_it_chu_khong_lay_hang_da_het(): void
    {
        // Hàng đã hết cần NHẬP GẤP, hàng sắp hết chỉ cần lên kế hoạch —
        // hai việc khác nhau nên phải là hai bộ lọc khác nhau.
        $cat = Category::factory()->create();
        Product::factory()->for($cat)->stock(3)->create(['name' => 'Sắp hết ba cái']);
        Product::factory()->for($cat)->stock(0)->create(['name' => 'Đã hết sạch']);
        Product::factory()->for($cat)->stock(50)->create(['name' => 'Còn nhiều']);

        $this->get('/admin/products?kho=sap-het')
            ->assertSee('Sắp hết ba cái')
            ->assertDontSee('Đã hết sạch')
            ->assertDontSee('Còn nhiều');
    }

    #[Test]
    public function loc_theo_danh_muc(): void
    {
        $hoa = Category::factory()->create(['name' => 'Hoa tươi']);
        $cay = Category::factory()->create(['name' => 'Cây cảnh']);
        Product::factory()->for($hoa)->create(['name' => 'Bó hồng đỏ']);
        Product::factory()->for($cay)->create(['name' => 'Trầu bà leo cột']);

        $this->get('/admin/products?category='.$hoa->id)
            ->assertSee('Bó hồng đỏ')
            ->assertDontSee('Trầu bà leo cột');
    }

    #[Test]
    public function loc_xong_bam_sang_trang_hai_van_giu_dieu_kien(): void
    {
        /*
         * Thiếu withQueryString() thì bấm sang trang 2 là mất sạch điều
         * kiện lọc, và admin quay về danh sách đầy đủ mà không hiểu vì
         * sao. Không lỗi, không cảnh báo.
         */
        $cat = Category::factory()->create();

        for ($i = 1; $i <= 25; $i++) {
            Product::factory()->for($cat)->create(['name' => 'Tulip số '.$i]);
        }

        Product::factory()->for($cat)->create(['name' => 'Chậu sứ không khớp']);

        $html = $this->get('/admin/products?q=Tulip')->assertOk()->getContent();

        /*
         * Soi LIÊN KẾT SANG TRANG 2, không soi cả trang: chuỗi "q=" còn
         * nằm trong ô tìm kiếm và sẽ làm bài xanh giả.
         */
        preg_match_all('#href="[^"]*page=2[^"]*"#', $html, $links);

        $this->assertNotEmpty($links[0], 'Phải có liên kết sang trang 2 để kiểm.');
        $this->assertStringContainsString(
            'q=Tulip',
            $links[0][0],
            'Liên kết phân trang phải mang theo từ khoá.',
        );

        $this->assertStringNotContainsString('Chậu sứ không khớp', $html);
    }

    #[Test]
    public function loc_khong_ra_ket_qua_thi_noi_dung_cau(): void
    {
        // "Chưa có sản phẩm nào" khi kho có 43 sản phẩm là câu SAI —
        // admin đọc rồi tưởng mất dữ liệu.
        Product::factory()->for(Category::factory())->create(['name' => 'Có tồn tại']);

        $this->get('/admin/products?q=khongcothat')
            ->assertOk()
            ->assertSee('Không có kết quả nào khớp với bộ lọc')
            ->assertDontSee('Chưa có sản phẩm nào');
    }

    #[Test]
    public function khong_loc_gi_thi_van_noi_chua_co_du_lieu(): void
    {
        $this->get('/admin/products')
            ->assertOk()
            ->assertSee('Chưa có sản phẩm nào');
    }

    // ================= ĐƠN HÀNG =================

    #[Test]
    public function tim_don_theo_ma_bo_qua_dau_gach(): void
    {
        /*
         * Khách đọc mã qua điện thoại kiểu nào cũng phải ra: "FP 260831
         * ABCD", "FP-260831-ABCD" hay "FP260831ABCD".
         */
        $khach = User::factory()->create();
        $this->actingAs($khach);

        $product = Product::factory()->for(Category::factory())->price('300000.00')->create();
        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1]);
        $this->post('/thanh-toan', [
            'recipient_name' => 'Nguyễn Thị Test', 'recipient_phone' => '0987654321',
            'shipping_address' => '1 Đường Test', 'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod', 'address_id' => '',
        ]);
        $this->post('/thanh-toan/dat-hang');

        $don = \App\Models\Order::latest('id')->first();
        $khongGach = str_replace('-', '', $don->order_number);

        $this->actingAs($this->admin());

        $this->get('/admin/orders?q='.$khongGach)->assertSee($don->order_number);
        $this->get('/admin/orders?q='.$don->order_number)->assertSee($don->order_number);
        $this->get('/admin/orders?q=0987654321')->assertSee($don->order_number);
        $this->get('/admin/orders?q=Nguyễn Thị Test')->assertSee($don->order_number);
    }

    // ================= NGƯỜI DÙNG =================

    #[Test]
    public function tim_nguoi_dung_theo_ten_va_email(): void
    {
        User::factory()->create(['name' => 'Lê Thị Mai Anh', 'email' => 'maianh@vidu.test']);
        User::factory()->create(['name' => 'Trần Quốc Bảo', 'email' => 'quocbao@vidu.test']);

        $this->get('/admin/users?q=Mai Anh')
            ->assertSee('Lê Thị Mai Anh')
            ->assertDontSee('Trần Quốc Bảo');

        $this->get('/admin/users?q=quocbao@')
            ->assertSee('Trần Quốc Bảo')
            ->assertDontSee('Lê Thị Mai Anh');
    }

    #[Test]
    public function loc_nguoi_dung_chua_xac_thuc_email(): void
    {
        // Khách gọi kêu "không vào được mục của tôi" — đây là chỗ nhìn
        // đầu tiên, vì chưa xác thực thì bị chặn khỏi trang cá nhân.
        User::factory()->create(['name' => 'Chưa Xác Thực', 'email_verified_at' => null]);
        User::factory()->create(['name' => 'Đã Xác Thực']);

        $this->get('/admin/users?xac_thuc=chua')
            ->assertSee('Chưa Xác Thực')
            ->assertDontSee('Đã Xác Thực');
    }

    // ================= MÃ GIẢM GIÁ =================

    #[Test]
    public function loc_ma_khach_dang_dung_duoc_khac_voi_trang_thai_dang_chay(): void
    {
        /*
         * Mã còn `status = active` nhưng đã QUA NGÀY KẾT THÚC thì khách
         * vẫn không dùng được. Lọc theo trạng thái không phát hiện ra —
         * đây đúng là lúc admin cần biết sự thật, vì khách đang gọi kêu
         * "mã của tôi báo lỗi".
         */
        Coupon::factory()->create(['code' => 'CONHIEULUC', 'name' => 'Mã còn hiệu lực']);
        Coupon::factory()->expired()->create(['code' => 'DAHETHAN', 'name' => 'Mã đã hết hạn']);

        $this->get('/admin/coupons?dung_duoc=co')
            ->assertOk()
            ->assertSee('CONHIEULUC')
            ->assertDontSee('DAHETHAN');
    }

    #[Test]
    public function tim_ma_giam_gia_theo_code(): void
    {
        Coupon::factory()->create(['code' => 'NOEL2026', 'name' => 'Giáng sinh']);
        Coupon::factory()->create(['code' => 'CHAOBAN', 'name' => 'Chào khách mới']);

        $this->get('/admin/coupons?q=NOEL')
            ->assertSee('NOEL2026')
            ->assertDontSee('CHAOBAN');
    }
}
