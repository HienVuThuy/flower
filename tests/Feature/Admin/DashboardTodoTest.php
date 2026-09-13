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

/**
 * "Việc cần làm" ở trang tổng quan quản trị.
 * ============================================================
 * Bản trước, thứ đầu tiên admin nhìn thấy là bốn con số: bao nhiêu danh
 * mục, bao nhiêu sản phẩm, bao nhiêu khách. Không con số nào nói cho họ
 * biết PHẢI LÀM GÌ — chúng gần như không đổi từ ngày này sang ngày khác,
 * và người ta học cách lướt qua.
 *
 * Điều được canh chừng ở đây: KHỐI NÀY PHẢI NÓI ĐÚNG SỰ THẬT. Một cảnh
 * báo hiện sai — hoặc không hiện khi đáng lẽ phải hiện — còn tệ hơn
 * không có cảnh báo, vì admin sẽ tin nó.
 */
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

        /*
         * forceFill CHỨ KHÔNG PHẢI create([...'status'...]).
         *
         * `status` cố ý không nằm trong $fillable — chỉ mã nguồn phía
         * cửa hàng mới đổi được, không phải dữ liệu gửi lên từ biểu mẫu.
         * Đưa vào create() thì Laravel BỎ QUA IM LẶNG, đơn ở lại trạng
         * thái mặc định, và bài kiểm thử xanh vì lý do sai.
         */
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

    /* ================= TIỀN CỦA NGƯỜI KHÁC ================= */

    #[Test]
    public function nhac_don_da_huy_ma_khach_da_tra_tien(): void
    {
        /*
         * Việc DUY NHẤT trong cả danh sách liên quan tới tiền của người
         * khác. Trước khi có khối này, không chỗ nào hiện nó ra — admin
         * chỉ phát hiện khi tình cờ mở đúng đơn đó.
         */
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

    /* ================= ĐƠN ĐỨNG IM ================= */

    #[Test]
    public function nhac_don_da_nhan_ma_chua_co_van_don(): void
    {
        /*
         * Đơn trả qua MoMo tự tạo vận đơn ngay sau khi thanh toán; đơn
         * COD thì KHÔNG — phải có người bấm. Trước đây không màn hình
         * nào hiện ra khoảng trống đó: đơn nằm ở "Đã xác nhận", trông y
         * hệt đơn đang chạy, mà thực tế chưa ai gọi shipper.
         */
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
        /*
         * Đơn đã giao mà không có mã vận đơn là chuyện bình thường —
         * khách tới lấy tại cửa hàng, hoặc đơn cũ trước khi nối GHN.
         * Đưa vào hàng đợi là dựng ra một việc không ai làm được, và
         * con số đó không bao giờ về 0.
         */
        $this->order(OrderStatus::Completed, PaymentStatus::Paid);

        $this->dashboard()->assertDontSee('đơn đã nhận nhưng chưa có vận đơn');
    }

    /* ================= TỒN KHO ================= */

    #[Test]
    public function nhac_hang_het_NHUNG_khong_tinh_hang_lam_theo_don(): void
    {
        /*
         * ĐÂY LÀ CHỖ DỄ SAI NHẤT, và cũng là chỗ một cảnh báo sai gây
         * hại nhất: hoa cưới có stock_quantity = 0 nhưng làm theo đơn,
         * không hề hết hàng. Gộp vào thì con số cảnh báo lúc nào cũng
         * khác không, và admin học cách bỏ qua nó — kể cả khi có hàng
         * thật sự hết.
         */
        $cat = Category::factory()->create();
        Product::factory()->for($cat)->madeToOrder()->create();

        $this->dashboard()->assertDontSee('mặt hàng đã hết nhưng vẫn đang bày bán');

        Product::factory()->for($cat)->stock(0)->create();

        $this->dashboard()->assertSee('mặt hàng đã hết nhưng vẫn đang bày bán');
    }

    #[Test]
    public function het_hang_cua_san_pham_DA_AN_khong_phai_la_viec(): void
    {
        // Khách không bấm vào được thì không mất đơn nào. Đưa vào danh
        // sách "phải xử lý hôm nay" là làm loãng chính danh sách đó.
        Product::factory()
            ->for(Category::factory())
            ->stock(0)
            ->create(['status' => 'draft']);

        $this->dashboard()->assertDontSee('mặt hàng đã hết nhưng vẫn đang bày bán');
    }

    #[Test]
    public function het_hang_o_QUY_CACH_van_bi_bat(): void
    {
        /*
         * MẤU CHỐT CỦA VIỆC ĐẾM BẰNG InventoryReport.
         *
         * Sản phẩm có quy cách giữ tồn ở TỪNG QUY CÁCH; cột
         * `products.stock_quantity` không phải thứ khách mua. Cách đếm
         * cũ (`products.stock_quantity <= 0`) bỏ sót đúng trường hợp
         * này: cột trên bảng sản phẩm vẫn là 100, trong khi quy cách
         * duy nhất đang bán đã hết sạch và khách không mua được gì.
         */
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

    /* ================= PHIẾU NHẬP ================= */

    #[Test]
    public function nhac_phieu_nhap_con_nhap(): void
    {
        /*
         * Lập phiếu KHÔNG cộng vào kho — phải bấm "Ghi sổ". Người lập bị
         * gọi đi giữa chừng là phiếu nằm mãi ở nháp, tồn kho hiển thị
         * thiếu, và trang Tồn kho giục nhập thêm đúng món đang chất
         * trong kho.
         */
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

    /* ================= ĐÁNH GIÁ ================= */

    #[Test]
    public function nhac_danh_gia_thap_chua_tra_loi(): void
    {
        $this->danhGia(1, null);

        $this->dashboard()->assertSee('đánh giá 1-2 sao chưa được trả lời');
    }

    #[Test]
    public function danh_gia_thap_DA_TRA_LOI_khong_con_la_viec(): void
    {
        // Một lời phàn nàn đã được trả lời không còn là việc phải làm.
        // Để nó lại trong hàng đợi là làm loãng phần còn lại.
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

    /* ================= LUẬT CHUNG CỦA HÀNG ĐỢI ================= */

    #[Test]
    public function het_viec_thi_noi_het_viec(): void
    {
        // Danh sách toàn "0 đơn chờ xác nhận" là danh sách không ai đọc,
        // và đọc mãi thành quen bỏ qua.
        $this->dashboard()
            ->assertOk()
            ->assertSee('Không có việc nào đang chờ')
            ->assertDontSee('đơn chờ xác nhận');
    }

    #[Test]
    public function moi_dong_dan_thang_toi_danh_sach_da_loc_san(): void
    {
        /*
         * Hiện con số rồi bắt admin tự đi lọc lại là bỏ dở việc giữa
         * chừng. Liên kết phải mang sẵn điều kiện lọc.
         */
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
        /*
         * Con số nói ĐANG CÓ GÌ; câu chú thích nói BỎ QUA THÌ MẤT GÌ.
         * Thiếu vế thứ hai thì người mới vào làm đọc "3 đơn chưa có vận
         * đơn" mà không biết điều đó nghĩa là hàng chưa đi.
         */
        $this->order(OrderStatus::Confirmed, PaymentStatus::Unpaid);

        $this->dashboard()->assertSee('Đơn COD không tự tạo vận đơn');
    }

    /* ================= ĐÍCH ĐẾN PHẢI KHỚP CON SỐ ================= */

    #[Test]
    public function dich_cua_dong_van_don_chi_hien_don_chua_co_van_don(): void
    {
        /*
         * Dòng việc nói "2 đơn chưa có vận đơn" rồi dẫn tới một danh
         * sách 40 đơn lẫn lộn là một lời hứa không giữ. Bộ lọc ở trang
         * đích phải cho ra ĐÚNG những đơn mà con số đã đếm.
         */
        $chua = $this->order(OrderStatus::Confirmed, PaymentStatus::Unpaid);
        $roi = $this->order(OrderStatus::Confirmed, PaymentStatus::Unpaid, ['ghn_order_code' => 'L8WA3V']);

        /*
         * ĐƠN ĐÃ GIAO XONG MÀ KHÔNG CÓ MÃ VẬN ĐƠN — chính là thứ làm lộ
         * lỗi trên dữ liệu thật: bản đầu bộ lọc chỉ soi "chưa có mã", nên
         * dòng việc nói 2 đơn mà bấm vào ra 41. Thiếu đơn này trong dữ
         * liệu thử thì bài kiểm thử xanh trong khi màn hình nói sai.
         */
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

    /* ================= LÔ HOA VÀ ĐỔI HÀNG ================= */

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
        /*
         * Quên đóng lô là giá vốn hoa thấp hơn sự thật. Trước đây chỉ
         * trang Lợi nhuận và trang Lô hoa đếm số này — chỉ ai cố ý đi
         * xem mới thấy.
         */
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
