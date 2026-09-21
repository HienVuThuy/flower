<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductType;
use App\Enums\SellingForm;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Exchange\ExchangeService;
use App\Services\Points\PointEarning;
use App\Services\Points\PointRedemption;
use App\Services\Shipping\ShippingRates;
use App\Services\Shop\ThamSoKinhDoanh;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Cài đặt › Tham số kinh doanh: admin đổi được, bỏ trống thì về mặc định, mọi nơi dùng đọc cùng một chỗ. */
class ThamSoKinhDoanhTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    private function luu(array $ts)
    {
        return $this->actingAs($this->admin())->put(route('admin.business-params.update'), ['ts' => $ts]);
    }

    #[Test]
    public function trang_tham_so_hien_du_nhom_va_gia_tri_mac_dinh(): void
    {
        $this->actingAs($this->admin())->get(route('admin.business-params.edit'))
            ->assertOk()
            ->assertSee('Ngưỡng &quot;Hoa cao cấp&quot;', false)
            ->assertSee('Giao hàng')
            ->assertSee('Điểm thưởng')
            ->assertSee('name="ts[catalog__cao_cap_tu]"', false)
            ->assertSee('value="800.000"', false);
    }

    #[Test]
    public function khach_thuong_khong_vao_duoc(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.business-params.edit'))->assertForbidden();
    }

    #[Test]
    public function doi_nguong_cao_cap_thi_bo_suu_tap_doi_theo_ngay(): void
    {
        $sp = Product::factory()->for(Category::factory())->price('600000.00')->create([
            'product_type' => ProductType::Flower, 'selling_form' => SellingForm::Bouquet,
        ]);

        $this->get(route('shop.products.index', ['bo-suu-tap' => 'cao-cap']))->assertDontSee($sp->name);

        $this->luu(['catalog__cao_cap_tu' => '500.000'])->assertSessionHasNoErrors();

        $this->assertSame(500000, ThamSoKinhDoanh::so('catalog.cao_cap_tu'));
        $this->get(route('shop.products.index', ['bo-suu-tap' => 'cao-cap']))->assertSee($sp->name);
    }

    #[Test]
    public function xoa_trang_thi_ve_mac_dinh_va_nhap_dung_mac_dinh_thi_khong_luu_de(): void
    {
        $this->luu(['kinh_doanh__han_doi_ngay' => '14']);
        $this->assertSame(14, ExchangeService::hanDoiNgay());
        $this->assertTrue(ThamSoKinhDoanh::daDoi('kinh_doanh.han_doi_ngay'));

        $this->luu(['kinh_doanh__han_doi_ngay' => '']);
        $this->assertSame(7, ExchangeService::hanDoiNgay());

        $this->luu(['kinh_doanh__han_doi_ngay' => '7']);
        $this->assertFalse(ThamSoKinhDoanh::daDoi('kinh_doanh.han_doi_ngay'), 'Bằng mặc định thì không ghi đè');
    }

    #[Test]
    public function gia_tri_sai_bi_chan_va_khong_luu_gi_ca(): void
    {
        $this->luu([
            'kinh_doanh__diem__phan_tram_toi_da' => '150',
            'kinh_doanh__han_doi_ngay' => 'mười',
            'catalog__moc_gia' => '1, 2, 3, 4, 5, 6, 7',
            'kinh_doanh__gio_toi_da_moi_mon' => '50',
        ])->assertSessionHasErrors([
            'ts.kinh_doanh__diem__phan_tram_toi_da',
            'ts.kinh_doanh__han_doi_ngay',
            'ts.catalog__moc_gia',
        ]);

        $this->assertSame(99, CartService::toiDaMoiMon(), 'Có lỗi thì không lưu ô nào');
    }

    #[Test]
    public function moc_gia_dung_lai_khoang_gia_o_bo_loc(): void
    {
        $this->luu(['catalog__moc_gia' => '1.000.000, 200.000'])->assertSessionHasNoErrors();

        $this->assertSame([200000, 1000000], ThamSoKinhDoanh::mocGia(), 'Tự sắp tăng dần');
        $this->assertSame([[null, 200000], [200000, 1000000], [1000000, null]], ThamSoKinhDoanh::khoangGia());
    }

    #[Test]
    public function phi_giao_diem_thuong_va_tran_ai_doc_tu_tham_so(): void
    {
        $this->luu([
            'shipping__zones__inner__fee' => '30.000',
            'shipping__free_from' => '0',
            'kinh_doanh__diem__dong_moi_diem_dung' => '200',
            'kinh_doanh__diem__dong_moi_diem_tich' => '20.000',
            'ai__moi_ngay' => '40',
        ])->assertSessionHasNoErrors();

        $gia = app(ShippingRates::class);
        $this->assertSame('30000.00', $gia->feeFor('Thành phố Hà Nội'));
        $this->assertSame('0.00', $gia->freeFrom());
        $this->assertSame('20000.00', PointRedemption::quyRaTien(100));
        $this->assertSame(5, PointEarning::diemChoTien('100000'));

        $tran = \Illuminate\Support\Facades\RateLimiter::limiter('tro-ly-ai')(\Illuminate\Http\Request::create('/', 'GET'));
        $this->assertSame(40, $tran[1]->maxAttempts);
    }

    #[Test]
    public function doi_tham_so_duoc_ghi_nhat_ky(): void
    {
        $this->luu(['kinh_doanh__nguong_chi_con' => '3']);

        $this->assertDatabaseHas('activity_logs', ['action' => 'settings.params']);
    }
}
