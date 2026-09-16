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
 * ⚠️ HỆ THỐNG DỰNG DỮ LIỆU HOÁ ĐƠN, KHÔNG PHÁT HÀNH HOÁ ĐƠN ĐIỆN TỬ.
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

    #[Test]
    public function khong_tich_thi_KHONG_tao_hoa_don(): void
    {
        $order = $this->datHang();

        $this->assertNull($order->invoice);
        $this->assertSame(0, Invoice::count());
    }

    #[Test]
    public function trang_don_hang_khong_hien_khoi_hoa_don_khi_khach_khong_yeu_cau(): void
    {
        $order = $this->datHang();

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertDontSee('Hoá đơn GTGT');
    }

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

    #[Test]
    public function tien_chua_thue_cong_tien_thue_bang_tong_thanh_toan(): void
    {
        $order = $this->datHang($this->congTy());
        $hoaDon = $order->invoice;

        $this->assertSame(
            (string) $hoaDon->grand_total,
            bcadd((string) $hoaDon->subtotal, (string) $hoaDon->tax_total, 2),
        );

        $this->assertSame((string) $order->grand_total, (string) $hoaDon->grand_total);

        $this->assertLessThan((float) $order->subtotal, (float) $hoaDon->subtotal);
    }

    #[Test]
    public function so_tren_chung_tu_khong_doi_khi_don_bi_sua(): void
    {
        $order = $this->datHang($this->congTy());
        $luucLap = (string) $order->invoice->tax_total;

        $order->forceFill([
            'grand_total' => '1.00',
            'tax_amount' => '0.07',
        ])->saveQuietly();

        $this->assertSame($luucLap, (string) $order->invoice->fresh()->tax_total);
    }

    #[Test]
    public function cong_ty_thieu_ma_so_thue_thi_bi_chan(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);

        $this->post('/thanh-toan', $this->form($this->congTy(['invoice_tax_code' => ''])))
            ->assertSessionHasErrors('invoice_tax_code');
    }

    #[Test]
    public function ma_so_thue_sai_dinh_dang_thi_bi_chan(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);

        $this->post('/thanh-toan', $this->form($this->congTy(['invoice_tax_code' => '12345'])))
            ->assertSessionHasErrors('invoice_tax_code');
    }

    #[Test]
    public function ma_so_thue_co_ma_chi_nhanh_van_hop_le(): void
    {
        $order = $this->datHang($this->congTy(['invoice_tax_code' => '0101234567-001']));

        $this->assertSame('0101234567-001', $order->invoice->buyer_tax_code);
    }

    #[Test]
    public function khong_tich_thi_KHONG_doi_hoi_gi_ca(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);

        $this->post('/thanh-toan', $this->form())
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    #[Test]
    public function chon_dia_chi_trong_so_KHONG_lam_mat_yeu_cau_hoa_don(): void
    {
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
        $order = $this->datHang($this->congTy());

        $order->invoice->fill(['issued_at' => now()]);

        $this->assertNull($order->invoice->issued_at);
    }
}
