<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Sắp xếp theo cột và thao tác hàng loạt ở khu quản trị. */
class SortAndBulkTest extends TestCase
{
    use RefreshDatabase;

    private Category $danhMuc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->danhMuc = Category::factory()->create();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    private function sanPham(string $ten, string $gia, int $kho = 10): Product
    {
        return Product::factory()
            ->for($this->danhMuc)
            ->price($gia)
            ->stock($kho)
            ->create(['name' => $ten]);
    }

    private function thuTu(string $url): array
    {
        $html = $this->actingAs($this->admin())->get($url)->assertOk()->getContent();

        preg_match_all('/<div class="fw-semibold">\s*([^<]+)/u', $html, $m);

        return array_map('trim', $m[1]);
    }

    #[Test]
    public function sap_duoc_theo_gia(): void
    {
        $this->sanPham('Đắt', '900000.00');
        $this->sanPham('Rẻ', '100000.00');
        $this->sanPham('Vừa', '500000.00');

        $this->assertSame(['Rẻ', 'Vừa', 'Đắt'], $this->thuTu('/admin/products?sap=gia'));

        $this->assertSame(
            ['Đắt', 'Vừa', 'Rẻ'],
            $this->thuTu('/admin/products?sap=gia&huong=giam'),
        );
    }

    #[Test]
    public function sap_duoc_theo_ton_kho(): void
    {
        $this->sanPham('Còn nhiều', '100000.00', 90);
        $this->sanPham('Sắp hết', '100000.00', 2);
        $this->sanPham('Vừa đủ', '100000.00', 30);

        $this->assertSame(
            ['Sắp hết', 'Vừa đủ', 'Còn nhiều'],
            $this->thuTu('/admin/products?sap=ton-kho'),
        );
    }

    #[Test]
    public function ten_cot_bia_dat_bi_bo_qua(): void
    {
        $this->sanPham('Một', '100000.00');
        $this->sanPham('Hai', '900000.00');

        $this->assertSame(
            $this->thuTu('/admin/products'),
            $this->thuTu('/admin/products?sap=password&huong=giam'),
        );
    }

    #[Test]
    public function huong_bia_dat_quay_ve_tang_dan(): void
    {
        $this->sanPham('Đắt', '900000.00');
        $this->sanPham('Rẻ', '100000.00');

        $this->assertSame(
            ['Rẻ', 'Đắt'],
            $this->thuTu('/admin/products?sap=gia&huong=;DROP TABLE products'),
        );

        $this->assertDatabaseCount('products', 2);
    }

    #[Test]
    public function sap_xep_giu_nguyen_bo_loc(): void
    {
        $this->sanPham('Còn hàng', '100000.00', 10);
        $this->sanPham('Hết hàng', '900000.00', 0);

        $ketQua = $this->thuTu('/admin/products?kho=het&sap=gia');

        $this->assertSame(['Hết hàng'], $ketQua);
    }

    #[Test]
    public function doi_trang_thai_nhieu_san_pham_mot_luc(): void
    {
        $ids = collect([
            $this->sanPham('A', '100000.00'),
            $this->sanPham('B', '200000.00'),
            $this->sanPham('C', '300000.00'),
        ])->pluck('id')->all();

        $this->actingAs($this->admin())
            ->post('/admin/products/hang-loat', ['viec' => 'an', 'ids' => $ids])
            ->assertRedirect();

        foreach ($ids as $id) {
            $this->assertSame('inactive', Product::find($id)->status);
        }
    }

    #[Test]
    public function thong_bao_noi_ro_so_luong_da_xu_ly(): void
    {
        $ids = collect([
            $this->sanPham('A', '100000.00'),
            $this->sanPham('B', '200000.00'),
            $this->sanPham('C', '300000.00'),
        ])->pluck('id')->all();

        $this->actingAs($this->admin())
            ->post('/admin/products/hang-loat', ['viec' => 'an', 'ids' => $ids])
            ->assertSessionHas('success', fn ($tin) => str_contains($tin, '3 sản phẩm'));
    }

    #[Test]
    public function viec_bia_dat_bi_tu_choi(): void
    {
        $product = $this->sanPham('Không được đụng vào', '100000.00');

        $this->actingAs($this->admin())
            ->post('/admin/products/hang-loat', [
                'viec' => 'xoa_sach_du_lieu',
                'ids' => [$product->id],
            ])
            ->assertSessionHasErrors('viec');

        $this->assertSame('active', $product->fresh()->status);
    }

    #[Test]
    public function id_khong_ton_tai_bi_tu_choi(): void
    {
        $product = $this->sanPham('Có thật', '100000.00');

        $this->actingAs($this->admin())
            ->post('/admin/products/hang-loat', [
                'viec' => 'an',
                'ids' => [$product->id, 999999],
            ])
            ->assertSessionHasErrors('ids.1');

        $this->assertSame('active', $product->fresh()->status,
            'Một id sai thì cả lô không được ghi.');
    }

    #[Test]
    public function khong_tich_dong_nao_thi_bao_loi(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/products/hang-loat', ['viec' => 'an', 'ids' => []])
            ->assertSessionHasErrors('ids');
    }

    #[Test]
    public function thao_tac_hang_loat_duoc_ghi_nhat_ky(): void
    {
        $ids = collect([
            $this->sanPham('A', '100000.00'),
            $this->sanPham('B', '200000.00'),
        ])->pluck('id')->all();

        $this->actingAs($this->admin())
            ->post('/admin/products/hang-loat', ['viec' => 'an', 'ids' => $ids]);

        $log = ActivityLog::latest('id')->first();

        $this->assertSame('product.bulk_updated', $log->action);
        $this->assertSame($ids, $log->properties['ids']);
    }

    #[Test]
    public function xoa_hang_loat_la_xoa_MEM(): void
    {
        $product = $this->sanPham('Sẽ bị xoá', '100000.00');

        $this->actingAs($this->admin())
            ->post('/admin/products/hang-loat', ['viec' => 'xoa', 'ids' => [$product->id]])
            ->assertRedirect();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    #[Test]
    public function khach_thuong_khong_lam_duoc_thao_tac_hang_loat(): void
    {
        $product = $this->sanPham('Không được đụng vào', '100000.00');

        $this->actingAs(User::factory()->create())
            ->post('/admin/products/hang-loat', ['viec' => 'xoa', 'ids' => [$product->id]])
            ->assertForbidden();

        $this->assertNotSoftDeleted('products', ['id' => $product->id]);
    }

    #[Test]
    public function thanh_thao_tac_dung_duoc_khi_KHONG_co_javascript(): void
    {
        $this->sanPham('Một', '100000.00');

        $html = $this->actingAs($this->admin())
            ->get('/admin/products')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-bulk-bar', $html);

        $this->assertDoesNotMatchRegularExpression(
            '/<div[^>]*data-bulk-bar[^>]*\shidden/',
            $html,
            'Thanh thao tác không được ẩn sẵn — không có JS thì không gì gỡ nó ra.',
        );
    }

    #[Test]
    public function o_tich_tro_ve_dung_bieu_mau_hang_loat(): void
    {
        $product = $this->sanPham('Một', '100000.00');

        $html = $this->actingAs($this->admin())
            ->get('/admin/products')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<form[^>]*id="bulk-form"/',
            $html,
        );

        $this->assertMatchesRegularExpression(
            '/form="bulk-form"[^>]*name="ids\[\]"[^>]*value="'.$product->id.'"/',
            $html,
            'Ô tích của từng dòng phải trỏ về biểu mẫu hàng loạt.',
        );
    }
}
