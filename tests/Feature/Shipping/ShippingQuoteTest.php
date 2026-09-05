<?php

namespace Tests\Feature\Shipping;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cước giao hàng: ai là người quyết định con số.
 * ============================================================
 * ĐÂY LÀ ĐIỂM KHÁC QUAN TRỌNG NHẤT SO VỚI TÀI LIỆU HƯỚNG DẪN.
 *
 * Tài liệu cho JavaScript tính phí rồi ghi tổng tiền vào một ô ẩn
 * (`total_price_input`), và máy chủ lấy con số đó làm tiền phải trả.
 * Cách đó nghĩa là TRÌNH DUYỆT QUYẾT ĐỊNH GIÁ: sửa một dòng trong
 * DevTools là được giao miễn phí đi Cà Mau, và không có bản ghi nào cho
 * thấy chuyện đã xảy ra.
 *
 * Ở đây trình duyệt chỉ gửi lên MÃ ĐỊA CHỈ. Máy chủ tự hỏi GHN ra tiền.
 * Cùng nguyên tắc với mã giảm giá.
 */
class ShippingQuoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.ghn.base_url', 'https://ghn.test/api');
        config()->set('services.ghn.token', 'token-thu');
        config()->set('services.ghn.shop_id', 1234);
        config()->set('services.ghn.from_district_id', 1482);

        Cache::flush();
    }

    /** GHN trả về một mức cước cố định để đối chiếu. */
    private function ghnBaoCuoc(int $cuoc): void
    {
        Http::fake([
            '*/shipping-order/fee' => Http::response([
                'code' => 200,
                'data' => ['total' => $cuoc],
            ]),
            '*' => Http::response(['code' => 200, 'data' => []]),
        ]);
    }

    private function sanPham(): Product
    {
        return Product::factory()
            ->for(Category::factory())
            ->price('300000.00')
            ->stock(20)
            ->create(['weight' => 500]);
    }

    /**
     * Đặt hàng qua đúng đường khách vẫn đi.
     *
     * `$them` để nhét thêm trường lạ vào biểu mẫu — dùng cho bài kiểm
     * tra chuyện máy chủ có nhận tiền từ client hay không.
     */
    private function datHang(array $them = []): Order
    {
        $product = $this->sanPham();

        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1]);

        $this->post('/thanh-toan', array_merge([
            'recipient_name' => 'Nguyễn Văn Kiểm Thử',
            'recipient_phone' => '0912345678',
            'recipient_email' => 'anaorin229@gmail.com',
            'shipping_address' => '12 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'shipping_district' => 'Quận Bắc Từ Liêm',
            'shipping_ward' => 'Phường Phú Diễn',
            'to_district_id' => 1482,
            'to_ward_code' => '11012',
            'payment_method' => 'cod',
            'address_id' => '',
        ], $them));

        $this->post('/thanh-toan/dat-hang')->assertRedirect();

        return Order::latest('id')->firstOrFail();
    }

    // ================================================================
    // Máy chủ tự tính, không nhận số từ trình duyệt
    // ================================================================

    #[Test]
    public function phi_giao_lay_tu_GHN_chu_khong_lay_tu_bieu_mau(): void
    {
        $this->actingAs(User::factory()->create());
        $this->ghnBaoCuoc(42900);

        /*
         * Biểu mẫu gửi kèm đủ kiểu tên gọi cho một mức phí bịa đặt.
         * Không cái nào được có tác dụng.
         */
        $order = $this->datHang([
            'shipping_fee' => 0,
            'total_price' => 1000,
            'ghn_total_fee' => 0,
            'grand_total' => 1000,
        ]);

        $this->assertSame(
            '42900.00',
            $order->shipping_fee,
            'Phí phải là con số GHN báo, không phải con số biểu mẫu gửi lên.',
        );

        $this->assertGreaterThan(
            1000,
            (float) $order->grand_total,
            'Tổng tiền phải do máy chủ cộng lại, không nhận từ trình duyệt.',
        );
    }

    #[Test]
    public function don_hang_luu_lai_ma_dia_gioi_de_con_tao_van_don(): void
    {
        /*
         * Không có hai mã này thì sau đó không tạo được vận đơn: GHN cần
         * đúng `to_district_id` và `to_ward_code`, và tên chữ "Quận Bắc
         * Từ Liêm" không suy ngược ra mã được.
         */
        $this->actingAs(User::factory()->create());
        $this->ghnBaoCuoc(42900);

        $order = $this->datHang();

        $this->assertSame(1482, $order->to_district_id);
        $this->assertSame('11012', $order->to_ward_code);

        // Và vẫn giữ tên chữ cho người đọc.
        $this->assertSame('Quận Bắc Từ Liêm', $order->shipping_district);
    }

    #[Test]
    public function luu_rieng_cuoc_GHN_va_tien_thu_cua_khach(): void
    {
        /*
         * `shipping_fee` là tiền cửa hàng THU của khách;
         * `ghn_total_fee` là tiền cửa hàng TRẢ cho GHN.
         *
         * Hai con số lệch nhau mỗi khi miễn phí giao cho đơn lớn. Gộp
         * làm một thì mất luôn khả năng đối soát: không biết tháng này
         * bù lỗ bao nhiêu tiền ship.
         */
        $this->actingAs(User::factory()->create());
        $this->ghnBaoCuoc(42900);

        $order = $this->datHang();

        $this->assertSame(42900, $order->ghn_total_fee);
    }

    // ================================================================
    // GHN hỏng thì vẫn đặt được hàng
    // ================================================================

    #[Test]
    public function GHN_khong_tra_loi_thi_lui_ve_bang_phi_theo_tinh(): void
    {
        /*
         * GHN sập, hết hạn mức, địa chỉ chưa hỗ trợ — cả ba đều có thật.
         * Khi đó KHÔNG được chặn khách đặt hàng, và cũng KHÔNG được cho
         * giao miễn phí. Bảng phí phẳng kém chính xác hơn, nhưng nó luôn
         * có một con số.
         */
        $this->actingAs(User::factory()->create());

        Http::fake(['*' => Http::response(['code' => 500], 500)]);

        $order = $this->datHang();

        $this->assertGreaterThan(0, (float) $order->shipping_fee,
            'Không được giao miễn phí chỉ vì GHN im lặng.');

        $this->assertSame(0, $order->ghn_total_fee,
            'Chưa hỏi được GHN thì cước GHN là 0, không bịa ra một con số.');
    }

    #[Test]
    public function dia_chi_khong_co_ma_GHN_van_dat_hang_duoc(): void
    {
        /*
         * Ba tình huống hợp lệ mà mã địa giới vắng mặt: GHN trục trặc
         * nên ô chọn không tải được; khách tắt JavaScript; địa chỉ lấy
         * từ sổ địa chỉ cũ lưu trước khi có GHN.
         *
         * Không cái nào là lý do để chặn một đơn hàng thật.
         */
        $this->actingAs(User::factory()->create());
        $this->ghnBaoCuoc(42900);

        $product = $this->sanPham();
        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1]);

        $this->post('/thanh-toan', [
            'recipient_name' => 'Nguyễn Văn Kiểm Thử',
            'recipient_phone' => '0912345678',
            'recipient_email' => 'anaorin229@gmail.com',
            'shipping_address' => '12 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'address_id' => '',
        ])->assertRedirect();

        $this->post('/thanh-toan/dat-hang')->assertRedirect();

        $order = Order::latest('id')->firstOrFail();

        $this->assertNull($order->to_district_id);
        $this->assertGreaterThan(0, (float) $order->shipping_fee);
    }

    // ================================================================
    // Khối lượng
    // ================================================================

    #[Test]
    public function khoi_luong_nhan_voi_so_luong(): void
    {
        /*
         * Mua mười chậu thì nặng gấp mười. Quên nhân là báo cước bằng
         * một phần mười thực tế — phần chênh cửa hàng chịu, và không ai
         * phát hiện cho tới lúc đối soát.
         */
        $this->actingAs(User::factory()->create());
        $this->ghnBaoCuoc(42900);

        $product = $this->sanPham();  // 500g mỗi cái

        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 4]);

        $this->post('/dia-gioi/tinh-cuoc', [
            'to_district_id' => 1482,
            'to_ward_code' => '11012',
        ])->assertOk();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'shipping-order/fee')) {
                return false;
            }

            return ($request->data()['weight'] ?? null) === 2000;
        });
    }

    // ================================================================
    // Endpoint báo giá
    // ================================================================

    #[Test]
    public function endpoint_bao_gia_khong_nhan_khoi_luong_tu_request(): void
    {
        /*
         * Nhận khối lượng từ trình duyệt thì khai 1 gram là ra cước rẻ
         * nhất — và vì đây cũng là con số hiện trên màn hình, khách sẽ
         * thấy một mức phí mà cửa hàng không bao giờ được hưởng.
         */
        $this->actingAs(User::factory()->create());
        $this->ghnBaoCuoc(42900);

        $product = $this->sanPham();
        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 2]);

        $this->post('/dia-gioi/tinh-cuoc', [
            'to_district_id' => 1482,
            'to_ward_code' => '11012',
            'weight' => 1,
        ])->assertOk();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'shipping-order/fee')) {
                return false;
            }

            return ($request->data()['weight'] ?? null) === 1000;
        });
    }

    #[Test]
    public function gio_rong_thi_khong_bao_gia(): void
    {
        $this->actingAs(User::factory()->create());
        $this->ghnBaoCuoc(42900);

        $this->post('/dia-gioi/tinh-cuoc', [
            'to_district_id' => 1482,
            'to_ward_code' => '11012',
        ])->assertStatus(422);
    }

    #[Test]
    public function GHN_hong_thi_endpoint_noi_ro_day_la_muc_tam(): void
    {
        /*
         * KHÔNG trả về 0: số 0 đọc là "miễn phí giao", và khách sẽ đặt
         * hàng với niềm tin đó rồi thấy tổng tiền khác ở bước cuối.
         */
        $this->actingAs(User::factory()->create());
        Http::fake(['*' => Http::response(['code' => 500], 500)]);

        $product = $this->sanPham();
        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1]);

        $res = $this->post('/dia-gioi/tinh-cuoc', [
            'to_district_id' => 1482,
            'to_ward_code' => '11012',
        ])->assertOk();

        $res->assertJsonPath('uoc_tinh', true);
        $this->assertGreaterThan(0, $res->json('data.total'));
    }

    // ================================================================
    // Tên tỉnh: hai nguồn không khớp nhau
    // ================================================================

    #[Test]
    public function ten_tinh_cua_GHN_duoc_chap_nhan_du_khong_co_trong_danh_sach_tinh(): void
    {
        /*
         * XUNG ĐỘT ĐO ĐƯỢC giữa hai nguồn:
         *
         *   App\Services\Shop\Provinces  →  34 tỉnh, tên SAU sáp nhập
         *                                     2025 ("Thành phố Hà Nội")
         *   Giao Hàng Nhanh               →  63 tỉnh, tên TRƯỚC sáp nhập
         *                                     ("Hà Nội", "Hòa Bình"...)
         *
         * Chỉ 28/63 mục trùng nhau. Giữ nguyên phép kiểm `Rule::in(...)`
         * thì khách chọn từ ô GHN xong bị báo "tỉnh/thành không hợp lệ"
         * cho hơn nửa số tỉnh trong nước.
         *
         * Có mã GHN nghĩa là chính GHN vừa xác nhận đó là nơi họ giao
         * tới — đối chiếu lại với một danh sách tĩnh là để một bảng dữ
         * liệu cũ hơn phủ quyết đơn vị vận chuyển.
         */
        $this->actingAs(User::factory()->create());
        $this->ghnBaoCuoc(42900);

        $product = $this->sanPham();
        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1]);

        $this->post('/thanh-toan', [
            'recipient_name' => 'Nguyễn Văn Kiểm Thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '12 Đường Thử',
            // "Hòa Bình" KHÔNG có trong danh sách 34 tỉnh sau sáp nhập.
            'shipping_province' => 'Hòa Bình',
            'shipping_district' => 'Thành phố Hòa Bình',
            'shipping_ward' => 'Phường Phương Lâm',
            'to_district_id' => 1566,
            'to_ward_code' => '210101',
            'payment_method' => 'cod',
            'address_id' => '',
        ])->assertSessionHasNoErrors();
    }

    #[Test]
    public function nhap_tay_thi_VAN_kiem_theo_danh_sach_tinh(): void
    {
        /*
         * Mặt còn lại. Không có mã GHN nghĩa là không còn gì kiểm giúp,
         * và phí sẽ tra theo bảng vùng — mà bảng vùng chỉ hiểu đúng
         * những tên trong danh sách. Nới lỏng ở đây là mở cửa cho một
         * chuỗi bất kỳ đi thẳng vào cột địa chỉ của đơn hàng.
         */
        $this->actingAs(User::factory()->create());
        $this->ghnBaoCuoc(42900);

        $product = $this->sanPham();
        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1]);

        $this->post('/thanh-toan', [
            'recipient_name' => 'Nguyễn Văn Kiểm Thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '12 Đường Thử',
            'shipping_province' => 'Tỉnh Không Có Thật',
            'payment_method' => 'cod',
            'address_id' => '',
        ])->assertSessionHasErrors('shipping_province');
    }
}