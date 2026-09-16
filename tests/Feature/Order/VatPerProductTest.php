<?php

namespace Tests\Feature\Order;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\TaxClass;
use App\Models\User;
use App\Services\Tax\TaxCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** VAT TÍNH THEO TỪNG DÒNG HÀNG, không tính trên tổng đơn. */
class VatPerProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set(TaxCalculator::SETTING_KEY, '0.08');
    }

    private function nhom(string $code, ?string $rate): TaxClass
    {
        return TaxClass::create([
            'code' => $code,
            'name' => 'Nhóm ' . $code,
            'rate' => $rate,
            'is_active' => true,
        ]);
    }

    private function hang(string $ten, string $gia, ?TaxClass $nhom = null): Product
    {
        return Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->price($gia)
            ->stock(50)
            ->create([
                'name' => $ten,
                'weight' => 500,
                'tax_class_id' => $nhom?->id,
            ]);
    }

    private function datHang(array $gio, array $themVaoForm = []): Order
    {
        $this->actingAs(User::factory()->create());

        foreach ($gio as $mon) {
            $this->post('/gio-hang', [
                'product_id' => $mon[0]->id,
                'quantity' => $mon[1] ?? 1,
            ]);
        }

        $this->post('/thanh-toan', array_merge([
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
        ], $themVaoForm));

        $this->post('/thanh-toan/dat-hang');

        return Order::latest('id')->firstOrFail()->load('items');
    }

    #[Test]
    public function san_pham_mang_thue_suat_cua_nhom_no_thuoc_ve(): void
    {
        $order = $this->datHang([[$this->hang('Chậu sứ', '110000.00', $this->nhom('vat_10', '0.10000'))]]);

        $dong = $order->items->first();

        $this->assertSame('0.10000', $dong->tax_rate, 'Dòng hàng không mang mức của nhóm thuế.');

        $this->assertSame('10000.00', $dong->tax_amount);
    }

    #[Test]
    public function chua_phan_loai_thi_lui_ve_muc_cua_hang_chu_khong_thanh_mien_thue(): void
    {
        $order = $this->datHang([[$this->hang('Cây chưa phân loại', '108000.00')]]);

        $dong = $order->items->first();

        $this->assertSame('0.08000', $dong->tax_rate);
        $this->assertNotNull($dong->tax_amount);
        $this->assertGreaterThan(0, (float) $dong->tax_amount);
    }

    #[Test]
    public function nhom_thue_da_tat_VAN_GIU_thue_suat_cho_san_pham_dang_gan(): void
    {
        $nhom = $this->nhom('vat_10', '0.10000');
        $nhom->update(['is_active' => false]);

        $order = $this->datHang([[$this->hang('Chậu sứ', '110000.00', $nhom)]]);

        $this->assertSame('0.10000', $order->items->first()->tax_rate);
    }

    #[Test]
    public function tat_nhom_khong_chiu_VAT_KHONG_bien_hang_thanh_hang_chiu_thue(): void
    {
        $nhom = $this->nhom('vat_exempt', null);
        $nhom->update(['is_active' => false]);

        $order = $this->datHang([[$this->hang('Bó hoa', '300000.00', $nhom)]]);

        $this->assertNull($order->items->first()->tax_rate);
    }

    #[Test]
    public function hang_khong_chiu_VAT_ghi_NULL_chu_khong_ghi_0(): void
    {
        $order = $this->datHang([[$this->hang('Bó hoa', '300000.00', $this->nhom('vat_exempt', null))]]);

        $dong = $order->items->first();

        $this->assertNull($dong->tax_rate, 'Hàng không chịu VAT bị ghi thành thuế suất 0%.');
        $this->assertNull($dong->tax_amount);
    }

    #[Test]
    public function chiu_0_phan_tram_van_la_hang_chiu_thue(): void
    {
        $order = $this->datHang([[$this->hang('Hàng xuất khẩu', '300000.00', $this->nhom('vat_0', '0.00000'))]]);

        $dong = $order->items->first();

        $this->assertSame('0.00000', $dong->tax_rate);
        $this->assertSame('0.00', $dong->tax_amount);
    }

    #[Test]
    public function don_hon_hop_tinh_theo_tung_dong_chu_khong_theo_tong(): void
    {
        $hoa = $this->hang('Bó hoa', '300000.00', $this->nhom('vat_exempt', null));
        $chau = $this->hang('Chậu sứ', '220000.00', $this->nhom('vat_10', '0.10000'));

        $order = $this->datHang([[$hoa], [$chau]]);

        $theoDong = $order->items->sum(fn ($i) => (float) $i->tax_amount);

        $this->assertEqualsWithDelta(20000.0, $theoDong, 0.01);

        $cachCu = 520000.0 - 520000.0 / 1.08;
        $this->assertGreaterThan(1000.0, abs($cachCu - $theoDong));
    }

    #[Test]
    public function thue_cua_don_bang_tong_cac_dong_cong_thue_phi_van_chuyen(): void
    {
        $order = $this->datHang([
            [$this->hang('Chậu sứ', '220000.00', $this->nhom('vat_10', '0.10000'))],
            [$this->hang('Cây chưa phân loại', '108000.00')],
        ]);

        $tongDong = '0.00';

        foreach ($order->items as $dong) {
            $tongDong = bcadd($tongDong, (string) ($dong->tax_amount ?? '0.00'), 2);
        }

        $this->assertSame(
            (string) $order->tax_amount,
            bcadd($tongDong, (string) $order->shipping_tax_amount, 2),
            'Thuế đầu đơn không khớp với tổng thuế các dòng cộng thuế phí giao.',
        );
    }

    #[Test]
    public function phi_van_chuyen_cung_co_phan_thue_cua_no(): void
    {
        $order = $this->datHang([[$this->hang('Cây nhỏ', '50000.00')]]);

        $this->assertGreaterThan(0, (float) $order->shipping_fee, 'Đơn này lẽ ra phải có phí giao.');
        $this->assertNotNull($order->shipping_tax_amount);
        $this->assertGreaterThan(0, (float) $order->shipping_tax_amount);
    }

    #[Test]
    public function ma_giam_gia_duoc_chia_het_cho_cac_dong(): void
    {
        Coupon::factory()->fixed('100000.00')->create(['code' => 'GIAM100K']);

        $order = $this->datHang([
            [$this->hang('Món A', '100000.00')],
            [$this->hang('Món B', '100000.00')],
            [$this->hang('Món C', '100000.00')],
        ], ['coupon_code' => 'GIAM100K']);

        $this->assertSame('100000.00', (string) $order->coupon_discount, 'Mã chưa được áp.');

        $tongChia = '0.00';

        foreach ($order->items as $dong) {
            $tongChia = bcadd($tongChia, (string) $dong->discount_amount, 2);
        }

        $this->assertSame(
            (string) $order->coupon_discount,
            $tongChia,
            'Phần giảm chia cho các dòng không cộng lại đúng bằng số tiền đã giảm.',
        );

        $this->assertSame('33333.33', (string) $order->items[0]->discount_amount);
        $this->assertSame('33333.34', (string) $order->items->last()->discount_amount);
    }

    #[Test]
    public function ma_giam_gia_lam_GIAM_tien_chiu_thue(): void
    {
        Coupon::factory()->fixed('110000.00')->create(['code' => 'GIAM110K']);

        $hang = fn () => $this->hang('Chậu sứ ' . uniqid(), '1100000.00', TaxClass::firstWhere('code', 'vat_10'));

        $this->nhom('vat_10', '0.10000');

        $khongMa = $this->datHang([[$hang()]]);
        $thueKhongMa = (float) $khongMa->items->first()->tax_amount;

        $coMa = $this->datHang([[$hang()]], ['coupon_code' => 'GIAM110K']);
        $thueCoMa = (float) $coMa->items->first()->tax_amount;

        $this->assertEqualsWithDelta(100000.0, $thueKhongMa, 0.01);
        $this->assertEqualsWithDelta(90000.0, $thueCoMa, 0.01);
    }

    #[Test]
    public function doi_phan_loai_sau_khi_ban_KHONG_viet_lai_don_cu(): void
    {
        $nhom = $this->nhom('vat_10', '0.10000');
        $sanPham = $this->hang('Chậu sứ', '110000.00', $nhom);

        $order = $this->datHang([[$sanPham]]);
        $thueLucBan = $order->items->first()->tax_amount;

        $nhom->update(['rate' => '0.05000']);
        $sanPham->update(['tax_class_id' => null]);

        $order->refresh()->load('items');

        $this->assertSame($thueLucBan, $order->items->first()->tax_amount);
        $this->assertSame('0.10000', $order->items->first()->tax_rate);
    }

    #[Test]
    public function doi_nhom_thue_KHONG_lam_khach_phai_tra_them_dong_nao(): void
    {
        $khongThue = $this->datHang([[$this->hang('Cây A', '500000.00', $this->nhom('vat_exempt', null))]]);
        $muoiPhanTram = $this->datHang([[$this->hang('Cây B', '500000.00', $this->nhom('vat_10', '0.10000'))]]);

        $this->assertSame(
            (string) $khongThue->grand_total,
            (string) $muoiPhanTram->grand_total,
            'Đổi nhóm thuế đã làm đổi số tiền khách phải trả.',
        );
    }

    #[Test]
    public function trang_don_hang_tach_thue_theo_tung_muc_khi_don_hon_hop(): void
    {
        $order = $this->datHang([
            [$this->hang('Bó hoa', '300000.00', $this->nhom('vat_exempt', null))],
            [$this->hang('Chậu sứ', '220000.00', $this->nhom('vat_10', '0.10000'))],
        ]);

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertSee('Trong đó thuế GTGT')
            ->assertSee('10%')
            ->assertSee('Không chịu VAT');
    }
}
