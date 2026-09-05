<?php

namespace Tests\Feature\Pricing;

use App\Enums\OrderStatus;
use App\Enums\PriceSignal;
use App\Enums\UserEventType;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\UserEvent;
use App\Services\Pricing\DemandSignals;
use App\Services\Pricing\PricingAdvisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cố vấn giá cho admin.
 * ============================================================
 * ĐÂY LÀ TÍNH NĂNG DỄ BỊA NHẤT TRONG CẢ DỰ ÁN, nên bài kiểm thử tập
 * trung vào chuyện KHÔNG NÓI hơn là chuyện nói.
 *
 * Một công cụ luôn đưa ra được vài đề xuất cho mọi cửa hàng thì trông
 * rất hữu ích và thực chất là đang đoán. Nguy hiểm hơn cả việc không có
 * công cụ nào, vì admin sẽ đổi giá bán thật theo nó.
 *
 * Vì vậy phần lớn các bài dưới đây khẳng định rằng công cụ IM LẶNG khi
 * chưa đủ căn cứ, và rằng mỗi đề xuất nói ra đều mang theo con số đã
 * sinh ra nó.
 */
class PricingAdvisorTest extends TestCase
{
    use RefreshDatabase;

    private Category $danhMuc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->danhMuc = Category::factory()->create(['kind' => 'plant', 'is_active' => true]);
    }

    private function sanPham(string $ten, array $ghiDe = []): Product
    {
        return Product::factory()->create(array_merge([
            'name' => $ten,
            'category_id' => $this->danhMuc->id,
        ], $ghiDe));
    }

    private function xem(Product $p, int $lan, UserEventType $loai = UserEventType::ProductView): void
    {
        for ($i = 0; $i < $lan; $i++) {
            UserEvent::query()->create([
                'session_id' => 'phien-' . $i,
                'event_type' => $loai,
                'product_id' => $p->id,
                'category_id' => $p->category_id,
                'created_at' => now()->subDays(2),
            ]);
        }
    }

    /** Tạo $lan đơn RIÊNG BIỆT, mỗi đơn một sản phẩm — tức là $lan người mua. */
    private function ban(Product $p, int $lan, int $ngayTruoc = 2, OrderStatus $trangThai = OrderStatus::Completed): void
    {
        for ($i = 0; $i < $lan; $i++) {
            // Không có OrderFactory trong dự án; dựng tay đúng như các
            // bài kiểm thử đơn hàng khác đang làm.
            $order = Order::create([
                'order_number' => 'FP-TEST-' . strtoupper(bin2hex(random_bytes(4))),
                'recipient_name' => 'Khách thử',
                'recipient_phone' => '0912345678',
                'shipping_address' => '1 Đường Thử',
                'shipping_province' => 'Thành phố Hà Nội',
                'payment_method' => 'cod',
                'subtotal' => '300000.00',
                'discount_total' => '0.00',
                'shipping_fee' => '25000.00',
                'coupon_discount' => '0.00',
                'grand_total' => '325000.00',
            ]);

            // `status` và `created_at` không nằm trong $fillable — gán
            // riêng, đúng cách các bài khác làm.
            $order->status = $trangThai;
            $order->created_at = now()->subDays($ngayTruoc);
            $order->save();

            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $p->id,
                'product_name' => $p->name,
                'quantity' => 1,
                'unit_base_price' => $p->base_price,
                'unit_price' => $p->base_price,
                'line_total' => $p->base_price,
            ]);
        }
    }

    /** @return \Illuminate\Support\Collection<int, \App\Services\Pricing\PriceSuggestion> */
    private function deXuat(int $ngay = 30)
    {
        return (new PricingAdvisor(new DemandSignals($ngay)))->suggest()['suggestions'];
    }

    private function tinHieuCho(string $ten): ?PriceSignal
    {
        return $this->deXuat()->first(fn ($s) => $s->product()->name === $ten)?->signal;
    }

    #[Test]
    public function it_luot_xem_thi_KHONG_de_xuat_gi_ca(): void
    {
        /*
         * BÀI QUAN TRỌNG NHẤT CỦA TỆP NÀY.
         *
         * Một sản phẩm có 3 lượt xem và 0 đơn KHÔNG nói gì về giá — nó
         * chỉ nói rằng gần như chưa ai nhìn thấy nó. Vấn đề ở đó là hiển
         * thị, không phải giá.
         *
         * Bỏ chặn này ra thì công cụ sẽ khuyên giảm giá gần như MỌI sản
         * phẩm của một cửa hàng mới, và admin sẽ giảm giá cả những món
         * đang hoàn toàn lành lặn.
         */
        $p = $this->sanPham('Món chưa ai thấy', ['stock_quantity' => 3]);
        $this->xem($p, 3);

        $this->assertNull($this->tinHieuCho('Món chưa ai thấy'));
    }

    #[Test]
    public function nhieu_luot_xem_ma_khong_ai_dat_thi_moi_de_xuat(): void
    {
        $p = $this->sanPham('Món nhiều người xem', ['stock_quantity' => 3]);
        $this->xem($p, 40);

        $this->assertSame(PriceSignal::InterestNoSale, $this->tinHieuCho('Món nhiều người xem'));
    }

    #[Test]
    public function moi_de_xuat_deu_mang_theo_con_so_sinh_ra_no(): void
    {
        /*
         * Công cụ này khuyên đổi giá bán. Admin phải kiểm lại được kết
         * luận mà không cần tin vào mã nguồn — nên một đề xuất không nêu
         * được bằng chứng thì không được phép tồn tại.
         */
        $p = $this->sanPham('Món nhiều người xem', ['stock_quantity' => 3]);
        $this->xem($p, 40);

        $deXuat = $this->deXuat()->firstOrFail();

        $this->assertNotEmpty($deXuat->evidence);
        $this->assertStringContainsString('40 lượt xem', implode(' | ', $deXuat->evidence));
    }

    #[Test]
    public function them_gio_nhieu_ma_it_don_thi_KHONG_do_cho_gia(): void
    {
        /*
         * Khách đã bỏ vào giỏ tức là ĐÃ CHẤP NHẬN GIÁ. Nghẽn nằm ở bước
         * thanh toán — phí ship, biểu mẫu, phương thức trả tiền.
         *
         * Gộp tình huống này chung với "không ai mua" là dẫn admin đi
         * giảm giá để chữa một vấn đề không nằm ở giá: tiền mất, mà lỗi
         * vẫn còn nguyên.
         */
        $p = $this->sanPham('Món hay bị bỏ giỏ', ['stock_quantity' => 3]);
        $this->xem($p, 40);
        $this->xem($p, 8, UserEventType::AddToCart);

        $this->assertSame(PriceSignal::CartNotCheckout, $this->tinHieuCho('Món hay bị bỏ giỏ'));
    }

    #[Test]
    public function ton_kho_nam_lau_khong_can_luot_xem(): void
    {
        /*
         * Ngoại lệ có chủ ý của ngưỡng lượt xem: "còn 20 cái trong kho,
         * 80 ngày không bán được" là một sự thật đầy đủ, không phụ thuộc
         * vào việc có ai xem hay không.
         */
        $p = $this->sanPham('Món nằm kho', [
            'stock_quantity' => 20,
            'created_at' => now()->subDays(120),
        ]);

        $this->xem($p, 2);
        $this->ban($p, 1, ngayTruoc: 80);

        $this->assertSame(PriceSignal::StaleStock, $this->tinHieuCho('Món nằm kho'));
    }

    #[Test]
    public function ton_kho_it_thi_nam_lau_cung_khong_dang_bao(): void
    {
        // Còn 1–2 cái thì dù nằm lâu cũng không phải chuyện cần một
        // chương trình khuyến mại.
        $p = $this->sanPham('Món gần hết', [
            'stock_quantity' => 2,
            'created_at' => now()->subDays(120),
        ]);

        $this->ban($p, 1, ngayTruoc: 80);

        $this->assertNull($this->tinHieuCho('Món gần hết'));
    }

    #[Test]
    public function don_da_huy_khong_duoc_tinh_la_ban_duoc(): void
    {
        /*
         * Đơn huỷ là ý muốn đã rút lại. Tính nó là bán được thì một sản
         * phẩm bị huỷ liên tục sẽ trông như đang bán chạy — và công cụ
         * sẽ im lặng đúng lúc cần lên tiếng nhất.
         */
        $p = $this->sanPham('Món toàn bị huỷ', ['stock_quantity' => 3]);
        $this->xem($p, 40);
        $this->ban($p, 5, trangThai: OrderStatus::Cancelled);

        $this->assertSame(PriceSignal::InterestNoSale, $this->tinHieuCho('Món toàn bị huỷ'));
    }

    #[Test]
    public function don_dang_giao_van_duoc_tinh_la_ban_duoc(): void
    {
        /*
         * Khác với trang Phân tích (chỉ tính đơn ĐÃ GIAO để ra doanh
         * thu). Ở đây câu hỏi là "có bao nhiêu người MUỐN mua", mà ý muốn
         * thể hiện ngay lúc đặt.
         *
         * Nếu chỉ tính đơn đã giao thì mọi đơn đang trên đường đều biến
         * mất khỏi số liệu, và một món vừa bán được mười đơn tuần này vẫn
         * bị báo là "không ai mua".
         */
        $p = $this->sanPham('Món đang giao', ['stock_quantity' => 3]);
        $this->xem($p, 40);
        $this->ban($p, 4, trangThai: OrderStatus::Shipping);

        $this->assertNull(
            $this->tinHieuCho('Món đang giao'),
            'Đơn đang giao đang bị bỏ qua — món này thật ra bán được.',
        );
    }

    #[Test]
    public function mau_mong_thi_phai_tu_bao_la_mau_mong(): void
    {
        // Cửa hàng mới có mươi đơn thì đề xuất là chỗ đáng đi xem lại,
        // không phải kết luận. Admin cần biết điều đó TRƯỚC khi đọc.
        $p = $this->sanPham('Món bất kỳ');
        $this->ban($p, 2);

        $ket = (new PricingAdvisor(new DemandSignals(30)))->suggest();

        $this->assertTrue($ket['thin_data']);
        $this->assertSame(2, $ket['total_orders']);
    }

    #[Test]
    public function bao_dung_so_san_pham_bi_bo_qua_vi_thieu_du_lieu(): void
    {
        /*
         * Con số này là lời thú nhận của công cụ về chính nó, và nó phải
         * đúng. Nếu nó im lặng về 39 sản phẩm mà chỉ khoe 10 đề xuất thì
         * admin sẽ tưởng 10 đề xuất đó là toàn cảnh cửa hàng.
         */
        foreach (range(1, 4) as $i) {
            $this->sanPham('Món ít xem ' . $i, ['stock_quantity' => 1]);
        }

        $duXem = $this->sanPham('Món đủ xem', ['stock_quantity' => 3]);
        $this->xem($duXem, 40);

        $ket = (new PricingAdvisor(new DemandSignals(30)))->suggest();

        $this->assertSame(5, $ket['examined']);
        $this->assertSame(4, $ket['skipped_too_few_views']);
        $this->assertCount(1, $ket['suggestions']);
    }

    #[Test]
    public function mat_bang_gia_chi_tinh_tu_hang_DA_BAN_DUOC(): void
    {
        /*
         * Mặt bằng giá phải là giá mà khách THẬT SỰ đã trả tiền. Gộp cả
         * hàng ế vào thì mốc tham chiếu bị kéo về phía đúng những mức giá
         * đang không hiệu quả — rồi công cụ lấy chính mốc hỏng đó đi
         * khuyên người khác.
         */
        // Ba món đã bán được, giá 100k / 200k / 300k → trung vị 200k.
        foreach ([100000, 200000, 300000] as $i => $gia) {
            $p = $this->sanPham('Đã bán ' . $i, ['base_price' => $gia . '.00']);
            $this->ban($p, 1);
        }

        /*
         * BA món chưa bán lần nào, giá rất cao.
         *
         * Phải là BA, không phải một. Bản đầu của bài này chỉ tạo một
         * món ế và nó XANH kể cả khi bỏ hẳn phép lọc "chỉ tính hàng đã
         * bán" — vì thêm đúng một giá trị vào dãy 100/150/200/300 vẫn
         * cho trung vị 200. Bài đo một thứ mà kết quả không đổi theo.
         *
         * Ba món ế thì trung vị bị kéo từ 200k lên 300k nếu phép lọc
         * biến mất, và bài mới thật sự canh được cái nó tuyên bố.
         */
        foreach (range(1, 3) as $i) {
            $this->sanPham('Chưa bán bao giờ ' . $i, ['base_price' => '9000000.00', 'stock_quantity' => 1]);
        }

        $ket = $this->sanPham('Món cần xét', ['base_price' => '150000.00', 'stock_quantity' => 3]);
        $this->xem($ket, 40);

        $deXuat = $this->deXuat()->first(fn ($s) => $s->product()->name === 'Món cần xét');

        $this->assertNotNull($deXuat);
        $this->assertNotNull($deXuat->anchor);
        $this->assertStringContainsString('200.000', $deXuat->anchor);
    }

    #[Test]
    public function duoi_ba_san_pham_da_ban_thi_khong_dua_ra_mat_bang(): void
    {
        /*
         * Trung vị của hai món là điểm giữa của đúng hai con số — không
         * phải một mặt bằng, chỉ là một phép chia đôi. Đưa ra làm mốc
         * tham chiếu là bịa một chuẩn từ chỗ chưa có chuẩn.
         */
        foreach ([100000, 300000] as $i => $gia) {
            $p = $this->sanPham('Đã bán ' . $i, ['base_price' => $gia . '.00']);
            $this->ban($p, 1);
        }

        $ket = $this->sanPham('Món cần xét', ['base_price' => '150000.00', 'stock_quantity' => 3]);
        $this->xem($ket, 40);

        $deXuat = $this->deXuat()->first(fn ($s) => $s->product()->name === 'Món cần xét');

        $this->assertNotNull($deXuat);
        $this->assertNull($deXuat->anchor, 'Đã dựng mặt bằng giá từ chỉ hai sản phẩm.');
    }

    #[Test]
    public function trang_de_xuat_gia_chi_admin_moi_vao_duoc(): void
    {
        /*
         * KHÁCH VÃNG LAI KIỂM TRƯỚC.
         *
         * `actingAs()` giữ nguyên người đăng nhập cho mọi request sau đó
         * trong cùng một bài. Đảo thứ tự thì request "khách vãng lai" vẫn
         * đang mang danh người dùng vừa đăng nhập, và bài đo nhầm thứ.
         */
        $this->get('/admin/de-xuat-gia')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())
            ->get('/admin/de-xuat-gia')
            ->assertForbidden();
    }
}
