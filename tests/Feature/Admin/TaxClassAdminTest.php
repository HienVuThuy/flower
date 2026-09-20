<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\TaxClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Admin tự chỉnh được nhóm thuế suất. */
class TaxClassAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    private function nhom(string $code, ?string $rate = '0.08000'): TaxClass
    {
        return TaxClass::create([
            'code' => $code,
            'name' => 'Nhóm ' . $code,
            'rate' => $rate,
            'is_active' => true,
        ]);
    }

    private function form(array $them = []): array
    {
        return array_merge([
            'theme' => 'default',
            'site_name' => 'Angevil',
            'site_hotline' => '0912345678',
            'site_email' => 'anaorin229@gmail.com',
            'site_address' => '41A Phú Diễn',
            'site_province' => 'Thành phố Hà Nội',
            'currency_code' => 'VND',
            'currency_symbol' => '₫',
            'currency_position' => 'after',
            'currency_decimals' => 0,
            'tax_rate_percent' => 8,
        ], $them);
    }

    #[Test]
    public function admin_doi_duoc_muc_cua_mot_nhom(): void
    {
        $nhom = $this->nhom('vat_10', '0.10000');

        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->form([
                'tax_classes' => [$nhom->id => ['rate_percent' => '8', 'is_active' => '1']],
            ]))
            ->assertRedirect();

        $this->assertSame('0.08000', $nhom->fresh()->rate);
    }

    #[Test]
    public function o_trong_nghia_la_KHONG_CHIU_VAT(): void
    {
        $nhom = $this->nhom('vat_exempt', '0.10000');

        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->form([
                'tax_classes' => [$nhom->id => ['rate_percent' => '', 'is_active' => '1']],
            ]));

        $this->assertNull($nhom->fresh()->rate);
        $this->assertTrue($nhom->fresh()->isExempt());
    }

    #[Test]
    public function so_0_nghia_la_CHIU_THUE_SUAT_0_chu_khong_phai_mien_thue(): void
    {
        $nhom = $this->nhom('vat_0', '0.10000');

        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->form([
                'tax_classes' => [$nhom->id => ['rate_percent' => '0', 'is_active' => '1']],
            ]));

        $this->assertSame('0.00000', $nhom->fresh()->rate);
        $this->assertFalse($nhom->fresh()->isExempt());
    }

    #[Test]
    public function bo_tich_thi_nhom_bi_tat(): void
    {
        $nhom = $this->nhom('vat_5', '0.05000');

        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->form([
                'tax_classes' => [$nhom->id => ['rate_percent' => '5', 'is_active' => '0']],
            ]));

        $this->assertFalse($nhom->fresh()->is_active);
    }

    #[Test]
    public function trang_cau_hinh_van_liet_ke_nhom_da_tat(): void
    {
        $nhom = $this->nhom('vat_5', '0.05000');
        $nhom->update(['is_active' => false]);

        $this->actingAs($this->admin())
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee($nhom->name);
    }

    #[Test]
    public function o_danh_dau_co_o_an_di_kem_de_bo_tich_luu_duoc(): void
    {
        $nhom = $this->nhom('vat_10', '0.10000');

        $this->actingAs($this->admin())
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee(
                '<input type="hidden" name="tax_classes[' . $nhom->id . '][is_active]" value="0">',
                escape: false,
            );
    }

    #[Test]
    public function bieu_mau_gui_thieu_KHONG_xoa_sach_cau_hinh_thue(): void
    {
        $giu = $this->nhom('vat_10', '0.10000');
        $gui = $this->nhom('vat_8', '0.08000');

        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->form([
                'tax_classes' => [$gui->id => ['rate_percent' => '8', 'is_active' => '1']],
            ]));

        $this->assertSame('0.10000', $giu->fresh()->rate, 'Nhóm không gửi lên đã bị sửa.');
        $this->assertTrue($giu->fresh()->is_active);
    }

    #[Test]
    public function id_nhom_thue_bia_tren_bieu_mau_bi_bo_qua(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->form([
                'tax_classes' => [999999 => ['rate_percent' => '99', 'is_active' => '1']],
            ]))
            ->assertRedirect();

        $this->assertSame(0, TaxClass::count());
    }

    #[Test]
    public function form_san_pham_moi_chon_nhom_thue(): void
    {
        $nhom = $this->nhom('vat_10', '0.10000');

        $this->actingAs($this->admin())
            ->get('/admin/products/create')
            ->assertOk()
            ->assertSee($nhom->name)
            ->assertSee('Mặc định cửa hàng');
    }

    #[Test]
    public function form_san_pham_KHONG_moi_chon_nhom_da_tat(): void
    {
        $nhom = $this->nhom('vat_10', '0.10000');
        $nhom->update(['is_active' => false]);

        $this->actingAs($this->admin())
            ->get('/admin/products/create')
            ->assertOk()
            ->assertDontSee($nhom->name);
    }

    #[Test]
    public function id_nhom_thue_khong_co_that_bi_chan_o_form_san_pham(): void
    {
        $danhMuc = Category::factory()->create();

        $this->actingAs($this->admin())
            ->post('/admin/products', [
                'category_id' => $danhMuc->id,
                'name' => 'Cây thử nghiệm',
                'base_price' => '100000',
                'status' => 'published',
                'tax_class_id' => 999999,
            ])
            ->assertSessionHasErrors('tax_class_id');

        $this->assertSame(0, Product::count());
    }
}
