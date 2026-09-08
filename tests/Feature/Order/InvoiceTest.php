<?php

namespace Tests\Feature\Order;

use App\Enums\InvoiceBuyerType;
use App\Enums\InvoiceStatus;
use App\Models\Address;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Tax\TaxCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dữ liệu hoá đơn GTGT.
 * ============================================================
 * ĐƠN HÀNG KHÔNG PHẢI HOÁ ĐƠN — đó là điều cả tệp này canh giữ.
 *
 *   Order   — dữ liệu thương mại: khách đặt gì, trả bao nhiêu, giao đâu.
 *   Invoice — chứng từ thuế: bán cho ai (mã số thuế), tiền chưa thuế
 *             bao nhiêu, thuế bao nhiêu.
 *
 * Ba lý do buộc phải tách, và mỗi lý do có một bài ở đây:
 *
 *   1. Phần lớn đơn KHÔNG có hoá đơn (khách lẻ mua bó hoa).
 *   2. Người mua trên hoá đơn khác người nhận hàng (công ty tặng đối tác).
 *   3. Số trên chứng từ phải ĐỨNG YÊN sau khi lập.
 *
 * ============================================================
 * ⚠️ HỆ THỐNG DỰNG DỮ LIỆU HOÁ ĐƠN, KHÔNG PHÁT HÀNH HOÁ ĐƠN ĐIỆN TỬ.
 *
 * Có một bài riêng canh đúng điều đó: trạng thái phải là "Chưa phát
 * hành" và giao diện phải NÓI RA. Để khách tưởng đã có hoá đơn là loại
 * nói dối tệ nhất — họ yên tâm không đòi nữa, rồi tới kỳ quyết toán mới
 * phát hiện không có chứng từ nào.
 */
class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set(TaxCalculator::SETTING_KEY, '0.08');
    }

    private function hang(string $gia = '500000.00'): Product
    {
        return Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->price($gia)
            ->stock(20)
            ->create(['weight' => 500]);
    }

    /** Bộ thông tin bước 1, hợp lệ và KHÔNG lấy hoá đơn. */
    private function form(array $them = []): array
    {
        return array_merge([
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
        ], $them);
    }

    /** Thông tin hoá đơn công ty, đủ và hợp lệ. */
    private function congTy(array $them = []): array
    {
        return array_merge([
            'want_invoice' => '1',
            'invoice_buyer_type' => 'company',
            'invoice_buyer_name' => 'Công ty TNHH Kiểm Thử',
            'invoice_tax_code' => '0101234567',
            'invoice_address' => '41A Phú Diễn, Bắc Từ Liêm, Hà Nội',
            'invoice_email' => 'anaorin229@gmail.com',
        ], $them);
    }

    private function datHang(array $them = []): Order
    {
        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);
        $this->post('/thanh-toan', $this->form($them));
        $this->post('/thanh-toan/dat-hang');

        return Order::latest('id')->firstOrFail();
    }

    /* ================= 1. KHÔNG YÊU CẦU THÌ KHÔNG CÓ ================= */

    #[Test]
    public function khong_tich_thi_KHONG_tao_hoa_don(): void
    {
        /*
         * Phần lớn khách mua một bó hoa không lấy hoá đơn. Tạo sẵn một
         * bản ghi cho mọi đơn là dựng ra hàng nghìn chứng từ chưa ai yêu
         * cầu — và mỗi cái đều tiêu một số hiệu.
         */
        $order = $this->datHang();

        $this->assertNull($order->invoice);
        $this->assertSame(0, Invoice::count());
    }

    #[Test]
    public function trang_don_hang_khong_hien_khoi_hoa_don_khi_khach_khong_yeu_cau(): void
    {
        // Một khối trống ghi "chưa có hoá đơn" chỉ làm khách tưởng mình
        // bỏ sót một bước nào đó.
        $order = $this->datHang();

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertDontSee('Hoá đơn GTGT');
    }

    /* ================= 2. TÍCH THÌ CÓ, VÀ ĐÚNG ================= */

    #[Test]
    public function tich_lay_hoa_don_thi_du_lieu_duoc_ghi_lai(): void
    {
        $order = $this->datHang($this->congTy());

        $hoaDon = $order->invoice;

        $this->assertNotNull($hoaDon, 'Khách đã yêu cầu nhưng không có dữ liệu hoá đơn nào.');
        $this->assertSame(InvoiceBuyerType::Company, $hoaDon->buyer_type);
        $this->assertSame('Công ty TNHH Kiểm Thử', $hoaDon->buyer_name);
        $this->assertSame('0101234567', $hoaDon->buyer_tax_code);
        $this->assertSame('anaorin229@gmail.com', $hoaDon->buyer_email);
    }

    #[Test]
    public function ca_nhan_KHONG_bi_gan_ma_so_thue(): void
    {
        /*
         * Cá nhân không có mã số thuế. Nếu người dùng gõ nhầm vào ô đó
         * rồi đổi sang "Cá nhân", con số kia KHÔNG được đi theo lên
         * chứng từ.
         */
        $order = $this->datHang([
            'want_invoice' => '1',
            'invoice_buyer_type' => 'personal',
            'invoice_buyer_name' => 'Nguyễn Văn A',
            'invoice_tax_code' => '0101234567',
            'invoice_email' => 'anaorin229@gmail.com',
        ]);

        $this->assertSame(InvoiceBuyerType::Personal, $order->invoice->buyer_type);
        $this->assertNull($order->invoice->buyer_tax_code);
    }

    /* ================= 3. BA CON SỐ PHẢI KHỚP ================= */

    #[Test]
    public function tien_chua_thue_cong_tien_thue_bang_tong_thanh_toan(): void
    {
        /*
         * ĐẲNG THỨC BẮT BUỘC CỦA MỘT HOÁ ĐƠN.
         *
         * Và `subtotal` của hoá đơn là số CHƯA thuế — khác
         * `orders.subtotal` (đã gồm thuế, vì giá niêm yết đã gồm thuế).
         * Cùng tên mà khác nghĩa là bẫy thật, nên phải có bài canh.
         */
        $order = $this->datHang($this->congTy());
        $hoaDon = $order->invoice;

        $this->assertSame(
            (string) $hoaDon->grand_total,
            bcadd((string) $hoaDon->subtotal, (string) $hoaDon->tax_total, 2),
        );

        $this->assertSame((string) $order->grand_total, (string) $hoaDon->grand_total);

        // Và số chưa thuế phải NHỎ HƠN số của đơn — nếu bằng nhau thì ai
        // đó đã chép thẳng orders.subtotal sang.
        $this->assertLessThan((float) $order->subtotal, (float) $hoaDon->subtotal);
    }

    /* ================= 4. BẢN CHỤP ĐỨNG YÊN ================= */

    #[Test]
    public function so_tren_chung_tu_khong_doi_khi_don_bi_sua(): void
    {
        /*
         * Đơn còn sửa được (admin đổi phí giao, huỷ một dòng hàng), còn
         * số trên chứng từ thì phải đứng yên kể từ lúc lập. Tính lại mỗi
         * lần đọc là để một chứng từ tự đổi nội dung sau lưng người đã
         * nhận nó.
         */
        $order = $this->datHang($this->congTy());
        $luucLap = (string) $order->invoice->tax_total;

        $order->forceFill([
            'grand_total' => '1.00',
            'tax_amount' => '0.07',
        ])->saveQuietly();

        $this->assertSame($luucLap, (string) $order->invoice->fresh()->tax_total);
    }

    /* ================= 5. XÁC THỰC ĐẦU VÀO ================= */

    #[Test]
    public function cong_ty_thieu_ma_so_thue_thi_bi_chan(): void
    {
        /*
         * Hoá đơn thiếu mã số thuế là hoá đơn công ty KHÔNG khấu trừ
         * được, và lúc phát hiện thì hàng đã giao xong. Chặn ở biểu mẫu
         * rẻ hơn nhiều.
         */
        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);

        $this->post('/thanh-toan', $this->form($this->congTy(['invoice_tax_code' => ''])))
            ->assertSessionHasErrors('invoice_tax_code');
    }

    #[Test]
    public function ma_so_thue_sai_dinh_dang_thi_bi_chan(): void
    {
        // 10 chữ số, hoặc 10 chữ số + 3 số chi nhánh. Kiểm dạng chỉ chặn
        // được lỗi gõ thiếu số — nhưng đó là lỗi phổ biến nhất.
        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);

        $this->post('/thanh-toan', $this->form($this->congTy(['invoice_tax_code' => '12345'])))
            ->assertSessionHasErrors('invoice_tax_code');
    }

    #[Test]
    public function ma_so_thue_co_ma_chi_nhanh_van_hop_le(): void
    {
        // Vế còn lại: dạng 10-3 là dạng THẬT của đơn vị trực thuộc, chặn
        // nhầm nó là chặn đúng nhóm khách hay lấy hoá đơn nhất.
        $order = $this->datHang($this->congTy(['invoice_tax_code' => '0101234567-001']));

        $this->assertSame('0101234567-001', $order->invoice->buyer_tax_code);
    }

    #[Test]
    public function khong_tich_thi_KHONG_doi_hoi_gi_ca(): void
    {
        /*
         * Ô mã số thuế bỏ trống mà vẫn qua được — đây là đường đi của
         * đại đa số khách. Nếu bài này đỏ nghĩa là cửa hàng vừa dựng một
         * bức tường ngay trước nút thanh toán cho tất cả mọi người.
         */
        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);

        $this->post('/thanh-toan', $this->form())
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    /* ================= 6. LỖI ĐÃ SỬA: CHỌN ĐỊA CHỈ TRONG SỔ ================= */

    #[Test]
    public function chon_dia_chi_trong_so_KHONG_lam_mat_yeu_cau_hoa_don(): void
    {
        /*
         * LỖI ĐÃ SỬA, tìm ra khi làm khối hoá đơn.
         *
         * `storeDetails()` gán đè cả mảng dữ liệu bằng địa chỉ lấy từ sổ:
         *
         *     $data = $address->toCheckoutData() + ['address_id' => ...];
         *
         * `toCheckoutData()` chỉ trả về thông tin người nhận, nên mọi thứ
         * khách vừa điền ở các bước khác — kể cả khối hoá đơn — biến mất
         * không dấu vết. Khách tích "cần hoá đơn", chọn địa chỉ trong sổ,
         * đặt hàng xong mới phát hiện không có hoá đơn nào.
         */
        $user = User::factory()->create();
        $this->actingAs($user);

        $address = Address::create([
            'user_id' => $user->id,
            'recipient_name' => 'Người nhận trong sổ',
            'recipient_phone' => '0987654321',
            'address_line' => '99 Đường Trong Sổ',
            'province' => 'Thành phố Hà Nội',
            'label' => 'home',
        ]);

        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);

        $this->post('/thanh-toan', $this->form($this->congTy(['address_id' => $address->id])));
        $this->post('/thanh-toan/dat-hang');

        $order = Order::latest('id')->firstOrFail();

        $this->assertNotNull(
            $order->invoice,
            'Chọn địa chỉ trong sổ đã xoá mất yêu cầu xuất hoá đơn.',
        );
        $this->assertSame('0101234567', $order->invoice->buyer_tax_code);
    }

    /* ================= 7. NÓI THẬT VỀ VIỆC PHÁT HÀNH ================= */

    #[Test]
    public function hoa_don_moi_lap_la_CHUA_phat_hanh(): void
    {
        $order = $this->datHang($this->congTy());

        $this->assertSame(InvoiceStatus::Draft, $order->invoice->status);
        $this->assertNull($order->invoice->issued_at);
    }

    #[Test]
    public function trang_don_hang_NOI_RO_hoa_don_chua_duoc_phat_hanh(): void
    {
        /*
         * BÀI QUAN TRỌNG NHẤT VỀ MẶT TRUNG THỰC.
         *
         * Hệ thống mới chỉ ghi nhận yêu cầu và dữ liệu; việc phát hành
         * hoá đơn điện tử hợp lệ đi qua nhà cung cấp dịch vụ, và cửa
         * hàng chưa tích hợp bước đó. Giao diện trông như đã có hoá đơn
         * là để khách yên tâm không đòi nữa — rồi tới kỳ quyết toán mới
         * phát hiện không có chứng từ nào.
         */
        $order = $this->datHang($this->congTy());

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertSee('Hoá đơn GTGT')
            ->assertSee('Chưa phát hành')
            ->assertSee('0101234567');
    }

    #[Test]
    public function issued_at_khong_dat_duoc_bang_mang_du_lieu(): void
    {
        /*
         * `issued_at` cố ý KHÔNG nằm trong $fillable: một mốc thời gian
         * chỉ được đặt bởi hành động thật đã xảy ra (phát hành qua nhà
         * cung cấp), không bao giờ bởi một mảng từ biểu mẫu.
         *
         * Nếu bài này đỏ, nghĩa là ai đó vừa mở đường cho một hoá đơn tự
         * nhận mình đã phát hành mà chưa có gì được phát hành cả.
         */
        $order = $this->datHang($this->congTy());

        $order->invoice->fill(['issued_at' => now()]);

        $this->assertNull($order->invoice->issued_at);
    }
}
