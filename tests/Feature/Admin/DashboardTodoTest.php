<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockReceiptStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\StockReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** "Việc cần làm" ở trang tổng quan quản trị. */
class DashboardTodoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    private function order(OrderStatus $status, PaymentStatus $payment, array $them = []): Order
    {
        $order = Order::create(array_merge([
            'order_number' => 'FP-TEST-'.strtoupper(bin2hex(random_bytes(3))),
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
        ], $them));

        $order->forceFill([
            'status' => $status,
            'payment_status' => $payment,
        ])->save();

        return $order;
    }

    private function dashboard()
    {
        return $this->actingAs($this->admin())->get('/admin/dashboard');
    }

    #[Test]
    public function nhac_don_da_huy_ma_khach_da_tra_tien(): void
    {
        $this->order(OrderStatus::Cancelled, PaymentStatus::Paid);

        $this->dashboard()
            ->assertOk()
            ->assertSee('đơn đã huỷ cần hoàn tiền cho khách');
    }

    #[Test]
    public function nhac_don_cho_xac_nhan(): void
    {
        $this->order(OrderStatus::Pending, PaymentStatus::Unpaid);

        $this->dashboard()->assertSee('đơn chờ xác nhận');
    }

    #[Test]
    public function nhac_don_da_nhan_ma_chua_co_van_don(): void
    {
        $this->order(OrderStatus::Confirmed, PaymentStatus::Unpaid);

        $this->dashboard()->assertSee('đơn đã nhận nhưng chưa có vận đơn');
    }

    #[Test]
    public function don_DA_CO_van_don_thi_khong_con_la_viec(): void
    {
        $this->order(OrderStatus::Confirmed, PaymentStatus::Unpaid, [
            'ghn_order_code' => 'L8WA3V',
        ]);

        $this->dashboard()->assertDontSee('đơn đã nhận nhưng chưa có vận đơn');
    }

    #[Test]
    public function don_da_giao_xong_khong_bi_doi_van_don(): void
    {
        $this->order(OrderStatus::Completed, PaymentStatus::Paid);

        $this->dashboard()->assertDontSee('đơn đã nhận nhưng chưa có vận đơn');
    }

    #[Test]
    public function nhac_hang_het_NHUNG_khong_tinh_hang_lam_theo_don(): void
    {
        $cat = Category::factory()->create();
        Product::factory()->for($cat)->madeToOrder()->create();

        $this->dashboard()->assertDontSee('mặt hàng đã hết nhưng vẫn đang bày bán');

        Product::factory()->for($cat)->stock(0)->create();

        $this->dashboard()->assertSee('mặt hàng đã hết nhưng vẫn đang bày bán');
    }

    #[Test]
    public function het_hang_cua_san_pham_DA_AN_khong_phai_la_viec(): void
    {
        Product::factory()
            ->for(Category::factory())
            ->stock(0)
            ->create(['status' => 'draft']);

        $this->dashboard()->assertDontSee('mặt hàng đã hết nhưng vẫn đang bày bán');
    }

    #[Test]
    public function het_hang_o_QUY_CACH_van_bi_bat(): void
    {
        $p = Product::factory()
            ->for(Category::factory())
            ->create(['status' => 'active', 'track_inventory' => true, 'stock_quantity' => 100]);

        ProductVariant::create([
            'product_id' => $p->id,
            'name' => 'Chậu sứ',
            'sku' => 'TEST-QC-1',
            'price' => '150000.00',
            'stock_quantity' => 0,
            'track_inventory' => true,
            'is_active' => true,
        ]);

        $this->dashboard()->assertSee('mặt hàng đã hết nhưng vẫn đang bày bán');
    }

    #[Test]
    public function nhac_phieu_nhap_con_nhap(): void
    {
        StockReceipt::create([
            'code' => 'NK-TEST-0001',
            'received_at' => now()->toDateString(),
        ]);

        $this->dashboard()->assertSee('phiếu nhập còn nháp, chưa cộng vào kho');
    }

    #[Test]
    public function phieu_da_ghi_so_khong_con_la_viec(): void
    {
        $phieu = StockReceipt::create([
            'code' => 'NK-TEST-0002',
            'received_at' => now()->toDateString(),
        ]);

        $phieu->forceFill(['status' => StockReceiptStatus::Posted, 'posted_at' => now()])->save();

        $this->dashboard()->assertDontSee('phiếu nhập còn nháp');
    }

    #[Test]
    public function nhac_danh_gia_thap_chua_tra_loi(): void
    {
        $this->danhGia(1, null);

        $this->dashboard()->assertSee('đánh giá 1-2 sao chưa được trả lời');
    }

    #[Test]
    public function danh_gia_thap_DA_TRA_LOI_khong_con_la_viec(): void
    {
        $this->danhGia(1, 'Cửa hàng xin lỗi và đã đổi cây mới cho anh/chị.');

        $this->dashboard()->assertDontSee('đánh giá 1-2 sao chưa được trả lời');
    }

    #[Test]
    public function danh_gia_5_sao_chua_tra_loi_khong_phai_viec_gap(): void
    {
        $this->danhGia(5, null);

        $this->dashboard()->assertDontSee('đánh giá 1-2 sao chưa được trả lời');
    }

    private function danhGia(int $sao, ?string $traLoi): Review
    {
        $review = Review::create([
            'product_id' => Product::factory()->for(Category::factory())->create()->id,
            'user_id' => User::factory()->create()->id,
            'rating' => $sao,
            'comment' => 'Nhận xét thử',
        ]);

        $review->forceFill(['admin_reply' => $traLoi, 'is_visible' => true])->save();

        return $review;
    }

    #[Test]
    public function het_viec_thi_noi_het_viec(): void
    {
        $this->dashboard()
            ->assertOk()
            ->assertSee('Không có việc nào đang chờ')
            ->assertDontSee('đơn chờ xác nhận');
    }

    #[Test]
    public function moi_dong_dan_thang_toi_danh_sach_da_loc_san(): void
    {
        $this->order(OrderStatus::Cancelled, PaymentStatus::Paid);

        $html = $this->dashboard()->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#href="[^"]*admin/orders\?[^"]*status=cancelled[^"]*payment=paid#',
            $html,
            'Dòng "cần hoàn tiền" phải dẫn tới danh sách đã lọc sẵn.',
        );
    }

    #[Test]
    public function moi_dong_noi_ro_vi_sao_dang_quan_tam(): void
    {
        $this->order(OrderStatus::Confirmed, PaymentStatus::Unpaid);

        $this->dashboard()->assertSee('Đơn COD không tự tạo vận đơn');
    }

    #[Test]
    public function dich_cua_dong_van_don_chi_hien_don_chua_co_van_don(): void
    {
        $chua = $this->order(OrderStatus::Confirmed, PaymentStatus::Unpaid);
        $roi = $this->order(OrderStatus::Confirmed, PaymentStatus::Unpaid, ['ghn_order_code' => 'L8WA3V']);

        $daGiao = $this->order(OrderStatus::Completed, PaymentStatus::Paid);

        $this->actingAs($this->admin())
            ->get(route('admin.orders.index', ['van_don' => 'cho-tao']))
            ->assertOk()
            ->assertSee($chua->order_number)
            ->assertDontSee($roi->order_number)
            ->assertDontSee($daGiao->order_number);
    }

    #[Test]
    public function dich_cua_dong_danh_gia_chi_hien_danh_gia_thap_chua_tra_loi(): void
    {
        $this->danhGia(1, null)->forceFill(['comment' => 'Cây héo ngay hôm sau'])->save();
        $this->danhGia(2, 'Đã đổi cây mới.')->forceFill(['comment' => 'Chậu bị nứt'])->save();

        $this->actingAs($this->admin())
            ->get(route('admin.reviews.index', ['sao' => 'thap', 'tra_loi' => 'chua']))
            ->assertOk()
            ->assertSee('Cây héo ngay hôm sau')
            ->assertDontSee('Chậu bị nứt');
    }

    #[Test]
    public function khach_thuong_khong_xem_duoc_trang_tong_quan(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    private function loHoa(int $ngayTruoc, string $trangThai = 'dang_dung'): \App\Models\FlowerLot
    {
        $loai = \App\Models\FlowerKind::firstOrCreate(['name' => 'Hồng thử'], ['default_unit' => 'bo']);

        $lo = new \App\Models\FlowerLot();
        $lo->forceFill([
            'code' => 'LH-' . uniqid(),
            'flower_kind_id' => $loai->id,
            'purchased_at' => now()->subDays($ngayTruoc)->toDateString(),
            'quantity' => '10.00',
            'unit' => 'bo',
            'total_cost' => '500000.00',
            'status' => $trangThai,
            'closed_at' => $trangThai === 'da_dong' ? now() : null,
        ])->save();

        return $lo;
    }

    private function doiHang(\App\Enums\ExchangeStatus $trangThai): \App\Models\Exchange
    {
        $don = $this->order(OrderStatus::Completed, PaymentStatus::Paid);

        $phieu = new \App\Models\Exchange();
        $phieu->forceFill([
            'code' => 'DH-' . strtoupper(bin2hex(random_bytes(3))),
            'order_id' => $don->id,
            'reason' => 'hang_hong',
            'status' => $trangThai,
        ])->save();

        return $phieu;
    }

    #[Test]
    public function nhac_lo_hoa_mo_qua_lau_ma_chua_dong(): void
    {
        $this->loHoa(\App\Services\Inventory\FlowerLotService::NGAY_NHAC_DONG + 2);

        $html = $this->dashboard()->assertOk()->assertSee('lô hoa mở quá')->getContent();

        $this->assertMatchesRegularExpression(
            '#href="[^"]*admin/lo-hoa\?[^"]*trang_thai=dang_dung#',
            $html,
            'Dòng lô quên đóng phải dẫn tới danh sách lô đang mở.',
        );
    }

    #[Test]
    public function lo_moi_lay_hoac_DA_DONG_khong_phai_la_viec(): void
    {
        $this->loHoa(2);
        $this->loHoa(30, 'da_dong');

        $this->dashboard()->assertOk()->assertDontSee('lô hoa mở quá');
    }

    #[Test]
    public function doi_hang_DA_NHAN_hang_tra_ma_chua_hoan_tat_la_viec_cua_cua_hang(): void
    {
        $this->doiHang(\App\Enums\ExchangeStatus::DaNhan);

        $html = $this->dashboard()
            ->assertOk()
            ->assertSee('phiếu đổi hàng đã nhận hàng trả nhưng chưa hoàn tất')
            ->assertDontSee('phiếu đổi hàng đang chờ khách gửi hàng về')
            ->getContent();

        $this->assertMatchesRegularExpression('#href="[^"]*admin/doi-hang\?[^"]*trang_thai=da_nhan#', $html);
    }

    #[Test]
    public function doi_hang_CHO_NHAN_la_muc_rieng(): void
    {
        $this->doiHang(\App\Enums\ExchangeStatus::ChoNhan);

        $html = $this->dashboard()
            ->assertOk()
            ->assertSee('phiếu đổi hàng đang chờ khách gửi hàng về')
            ->assertDontSee('đã nhận hàng trả nhưng chưa hoàn tất')
            ->getContent();

        $this->assertMatchesRegularExpression('#href="[^"]*admin/doi-hang\?[^"]*trang_thai=cho_nhan#', $html);
    }

    #[Test]
    public function doi_hang_HOAN_TAT_hoac_HUY_khong_con_la_viec(): void
    {
        $this->doiHang(\App\Enums\ExchangeStatus::HoanTat);
        $this->doiHang(\App\Enums\ExchangeStatus::Huy);

        $this->dashboard()->assertOk()->assertDontSee('phiếu đổi hàng');
    }
}
