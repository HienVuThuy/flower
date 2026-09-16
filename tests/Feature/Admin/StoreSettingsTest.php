<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Services\Shop\Money;
use App\Services\Shop\StoreProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Admin tự sửa được nhận diện, tiền tệ và thuế. */
class StoreSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    private function duLieu(array $ghiDe = []): array
    {
        return array_merge([
            'theme' => 'default',
            'site_name' => 'Cửa hàng thử',
            'site_tagline' => 'Hoa và cây',
            'site_hotline' => '0912345678',
            'site_email' => 'thu@example.com',
            'site_address' => '1 Đường Thử',
            'site_province' => 'Thành phố Hà Nội',
            'currency_code' => 'VND',
            'currency_symbol' => '₫',
            'currency_position' => 'after',
            'currency_decimals' => 0,
            'tax_rate_percent' => 8,
        ], $ghiDe);
    }

    #[Test]
    public function doi_ten_cua_hang_lan_ra_moi_noi(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->duLieu(['site_name' => 'Chồi Xanh']))
            ->assertRedirect();

        $this->assertSame('Chồi Xanh', StoreProfile::name());

        $this->get('/san-pham')->assertOk()->assertSee('Chồi Xanh');
    }

    #[Test]
    public function luu_cau_hinh_khong_lam_mat_logo_dang_co(): void
    {
        Setting::set('site_logo', 'branding/logo-cua-toi.png');

        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->duLieu(['site_hotline' => '0900000000']))
            ->assertRedirect();

        $this->assertSame('branding/logo-cua-toi.png', StoreProfile::get('site_logo'));
    }

    #[Test]
    public function tai_logo_len_thi_no_thay_hinh_mac_dinh(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('Máy chạy kiểm thử chưa bật GD.');
        }

        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->duLieu([
                'site_logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            ]))
            ->assertRedirect();

        $path = StoreProfile::get('site_logo');

        $this->assertNotNull($path);
        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertStringContainsString($path, (string) StoreProfile::logoUrl());
    }

    #[Test]
    public function khong_nhan_logo_dinh_dang_svg(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->duLieu([
                'site_logo' => UploadedFile::fake()->createWithContent(
                    'logo.svg',
                    '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
                ),
            ]))
            ->assertSessionHasErrors('site_logo');

        $this->assertNull(StoreProfile::get('site_logo'));
    }

    #[Test]
    public function hotline_KHONG_phai_so_thi_khong_hien_o_dau_ca(): void
    {
        Setting::set('site_hotline', 'demo');

        $this->assertNull(StoreProfile::hotline());

        $this->get('/san-pham')->assertOk()->assertDontSee('demo');
        $this->get(route('shop.orders.lookup'))->assertOk()->assertDontSee('Gọi cho cửa hàng');

        $this->actingAs($this->admin())
            ->get('/admin/dashboard')
            ->assertSee('không phải số điện thoại');
    }

    #[Test]
    public function hotline_la_so_thi_hien_va_bam_goi_duoc(): void
    {
        Setting::set('site_hotline', '0912 345 678');

        $this->assertSame('0912 345 678', StoreProfile::hotline());
        $this->get('/san-pham')->assertOk()->assertSee('href="tel:0912345678"', false);
    }

    #[Test]
    public function khong_luu_duoc_hotline_khong_phai_so(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->duLieu(['site_hotline' => 'demo']))
            ->assertSessionHasErrors('site_hotline');

        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->duLieu(['site_hotline' => '']))
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function ten_cua_hang_khong_duoc_de_trong(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->duLieu(['site_name' => '']))
            ->assertSessionHasErrors('site_name');
    }

    #[Test]
    public function don_vi_tien_KHOA_O_VND_du_bieu_mau_gui_len_USD(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->duLieu([
                'currency_code' => 'USD',
                'currency_symbol' => '$',
                'currency_position' => 'before',
                'currency_decimals' => 2,
            ]))
            ->assertRedirect();

        $this->assertSame('1.234₫', Money::format(1234));
        $this->assertSame('VND', Money::code());
    }

    #[Test]
    public function gia_tri_tien_te_cu_con_trong_bang_bi_bo_qua(): void
    {
        Setting::set('currency_code', 'USD');
        Setting::set('currency_symbol', '$');
        Setting::set('currency_decimals', '2');

        $this->assertSame('500.000₫', Money::format(500000));
    }

    #[Test]
    public function thue_suat_nhap_theo_phan_tram_luu_theo_thap_phan(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->duLieu(['tax_rate_percent' => 10]))
            ->assertRedirect();

        $this->assertSame('0.10000', Setting::get('tax_rate'));
        $this->assertSame('10', app(\App\Services\Tax\TaxCalculator::class)->ratePercent());
    }

    #[Test]
    public function khong_nhan_thue_suat_tu_100_phan_tram_tro_len(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->duLieu(['tax_rate_percent' => 150]))
            ->assertSessionHasErrors('tax_rate_percent');
    }

    #[Test]
    public function trang_cau_hinh_noi_ro_vi_sao_mot_cong_thanh_toan_chua_dung_duoc(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Hình thức thanh toán')
            ->assertSee('Thanh toán khi nhận hàng (COD)');
    }
}
