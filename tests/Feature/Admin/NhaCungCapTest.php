<?php

namespace Tests\Feature\Admin;

use App\Enums\SupplierKind;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Category;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Nhà cung cấp: nơi cửa hàng lấy hàng, thành một bảng. */
class NhaCungCapTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    private function them(array $ghiDe = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())->post('/admin/nha-cung-cap', array_merge([
            'name' => 'Vựa hoa Quảng Bá',
            'kind' => SupplierKind::Vua->value,
            'phone' => '0912345678',
            'is_active' => '1',
        ], $ghiDe));
    }

    #[Test]
    public function khong_cho_hai_dong_cung_mot_ten(): void
    {
        $this->them()->assertSessionHasNoErrors()->assertRedirect();

        $this->them()
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Supplier::count());
    }

    #[Test]
    public function ten_bi_cat_khoang_trang_thua_truoc_khi_luu(): void
    {
        $this->them(['name' => '  Vựa hoa Quảng Bá  '])->assertRedirect();

        $this->assertSame('Vựa hoa Quảng Bá', Supplier::first()->name);
    }

    #[Test]
    public function sua_chinh_minh_thi_khong_bi_bao_trung_ten(): void
    {
        $this->them()->assertRedirect();
        $ncc = Supplier::firstOrFail();

        $this->actingAs($this->admin())
            ->put('/admin/nha-cung-cap/' . $ncc->id, [
                'name' => $ncc->name,
                'kind' => SupplierKind::NongDan->value,
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(SupplierKind::NongDan, $ncc->fresh()->kind);
    }

    #[Test]
    public function khong_co_duong_dan_nao_xoa_nha_cung_cap(): void
    {
        $duong = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'admin/nha-cung-cap'))
            ->map(fn ($r) => implode('|', $r->methods()))
            ->implode(' ');

        $this->assertStringNotContainsString('DELETE', $duong);
    }

    #[Test]
    public function tat_di_thi_khong_con_hien_o_o_chon_nhung_van_doc_duoc(): void
    {
        $this->them(['name' => 'Vựa còn lấy'])->assertRedirect();
        $this->them(['name' => 'Vựa đã ngừng'])->assertRedirect();

        $ngung = Supplier::where('name', 'Vựa đã ngừng')->firstOrFail();

        $this->actingAs($this->admin())->put('/admin/nha-cung-cap/' . $ngung->id, [
            'name' => $ngung->name,
            'kind' => $ngung->kind->value,
        ])->assertRedirect();

        $this->assertFalse($ngung->fresh()->is_active);

        $html = $this->actingAs($this->admin())
            ->get('/admin/nhap-kho/create')
            ->assertOk()
            ->getContent();

        $con = Supplier::where('name', 'Vựa còn lấy')->firstOrFail();

        $this->assertStringContainsString('value="' . $con->id . '"', $html);
        $this->assertStringNotContainsString('value="' . $ngung->id . '"', $html);

        $this->actingAs($this->admin())
            ->get('/admin/nha-cung-cap')
            ->assertOk()
            ->assertSee('Vựa đã ngừng');
    }

    #[Test]
    public function phieu_nhap_chup_lai_TEN_chu_khong_chi_giu_id(): void
    {
        $this->them(['name' => 'Vựa tên cũ'])->assertRedirect();
        $ncc = Supplier::firstOrFail();

        $sp = Product::factory()->for(Category::factory())->stock(5)->create();

        $this->actingAs($this->admin())->post('/admin/nhap-kho', [
            'supplier_id' => $ncc->id,
            'received_at' => now()->toDateString(),
            'items' => [
                ['mat_hang' => (string) $sp->id, 'quantity' => 3, 'unit_cost' => 50000],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $phieu = StockReceipt::firstOrFail();

        $this->assertSame(1, $phieu->items()->count());

        $this->assertSame($ncc->id, $phieu->supplier_id);
        $this->assertSame('Vựa tên cũ', $phieu->supplier);

        $ncc->update(['name' => 'Vựa tên mới']);

        $this->assertSame('Vựa tên cũ', $phieu->fresh()->tenNhaCungCap());
    }

    #[Test]
    public function de_trong_nha_cung_cap_van_lap_duoc_phieu(): void
    {
        $sp = Product::factory()->for(Category::factory())->stock(5)->create();

        $this->actingAs($this->admin())->post('/admin/nhap-kho', [
            'received_at' => now()->toDateString(),
            'items' => [['mat_hang' => (string) $sp->id, 'quantity' => 1, 'unit_cost' => 1000]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertNull(StockReceipt::firstOrFail()->supplier_id);
    }

    #[Test]
    public function id_nha_cung_cap_khong_co_that_thi_bi_tu_choi(): void
    {
        $sp = Product::factory()->for(Category::factory())->stock(5)->create();

        $this->actingAs($this->admin())->post('/admin/nhap-kho', [
            'supplier_id' => 999999,
            'received_at' => now()->toDateString(),
            'items' => [['mat_hang' => (string) $sp->id, 'quantity' => 1, 'unit_cost' => 1000]],
        ])->assertSessionHasErrors('supplier_id');

        $this->assertSame(0, StockReceipt::count());
    }

    #[Test]
    public function nhan_vien_kho_vao_duoc_con_khach_thi_khong(): void
    {
        $nv = User::factory()->create();
        $nv->role = UserRole::Staff;
        $nv->save();

        $this->actingAs($nv)->get('/admin/nha-cung-cap')->assertOk();

        $khach = User::factory()->create();
        $khach->role = UserRole::Customer;
        $khach->save();

        $this->actingAs($khach)->get('/admin/nha-cung-cap')->assertForbidden();
    }
}
