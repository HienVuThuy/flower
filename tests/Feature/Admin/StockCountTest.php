<?php

namespace Tests\Feature\Admin;

use App\Enums\StockCountStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockCount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phiếu kiểm kê kho.
 * ============================================================
 * Chỗ dễ sai nhất — và sai mà không ai thấy — là LÚC GHI SỔ: gán số đếm được
 * thay vì cộng chênh lệch thì mọi món bán ra giữa lúc đếm và lúc ghi sổ được
 * "trả lại" vào kho. Phần lớn bài ở đây canh chuyện đó.
 */
class StockCountTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    private function sp(int $ton, bool $theoDoi = true): Product
    {
        return Product::factory()->for(Category::factory())->create([
            'status' => 'active',
            'track_inventory' => $theoDoi,
            'stock_quantity' => $ton,
        ]);
    }

    private function lap(array $dem, ?User $ai = null)
    {
        return $this->actingAs($ai ?? $this->admin())->post(route('admin.stock-counts.store'), [
            'counted_at' => now()->toDateString(),
            'dem' => $dem,
        ]);
    }

    private function ghiSo(StockCount $p)
    {
        return $this->actingAs($this->admin())->post(route('admin.stock-counts.post', $p));
    }

    #[Test]
    public function ton_he_thong_chup_o_may_chu_khong_nhan_tu_bieu_mau_va_chua_doi_kho(): void
    {
        $sp = $this->sp(10);

        $this->lap([$sp->id . ':' => ['counted' => 7, 'system_quantity' => 999]])->assertRedirect();

        $dong = StockCount::first()->items->first();

        $this->assertSame(10, $dong->system_quantity, 'Số hệ thống phải đọc từ cơ sở dữ liệu.');
        $this->assertSame(7, $dong->counted_quantity);
        $this->assertSame(10, $sp->fresh()->stock_quantity, 'Lập phiếu chưa được đổi kho.');
    }

    #[Test]
    public function de_trong_la_khong_dem_con_0_la_dem_duoc_0(): void
    {
        $a = $this->sp(5);
        $b = $this->sp(5);

        $this->lap([$a->id . ':' => ['counted' => ''], $b->id . ':' => ['counted' => 0]]);

        $items = StockCount::first()->items;

        $this->assertCount(1, $items);
        $this->assertSame($b->id, $items->first()->product_id);
    }

    #[Test]
    public function ghi_so_CONG_CHENH_LECH_khong_gan_so_dem(): void
    {
        /*
         * Đếm lúc hệ thống ghi 10, thấy 7 (mất 3). Trước khi ghi sổ, bán thêm
         * 1 → hệ thống còn 9. Gán số đếm: 7 — trả lại món vừa bán. Cộng chênh
         * lệch: 9 + (7 − 10) = 6 — đúng.
         */
        $sp = $this->sp(10);
        $this->lap([$sp->id . ':' => ['counted' => 7, 'reason' => '3 chậu vỡ']]);

        $sp->decrement('stock_quantity', 1);

        $this->ghiSo(StockCount::first())->assertSessionHas('success');

        $this->assertSame(6, $sp->fresh()->stock_quantity);
        $this->assertSame(-3, StockCount::first()->items->first()->applied_difference);
        $this->assertSame(StockCountStatus::Posted, StockCount::first()->status);
    }

    #[Test]
    public function ton_se_am_thi_tu_choi_va_KHONG_doi_dong_nao(): void
    {
        /*
         * Hai dòng trong một phiếu; dòng thứ hai sẽ âm. Cả phiếu một
         * transaction: dòng đầu cũng không được đổi, phiếu vẫn là nháp.
         */
        $tot = $this->sp(10);
        $am = $this->sp(3);

        $this->lap([
            $tot->id . ':' => ['counted' => 8],
            $am->id . ':' => ['counted' => 0],
        ]);

        $am->update(['stock_quantity' => 1]); // đã bán 2 sau lúc đếm

        $this->ghiSo(StockCount::first())->assertSessionHas('error');

        $this->assertSame(10, $tot->fresh()->stock_quantity);
        $this->assertSame(1, $am->fresh()->stock_quantity);
        $this->assertSame(StockCountStatus::Draft, StockCount::first()->status);
    }

    #[Test]
    public function ghi_so_dung_mot_lan(): void
    {
        $sp = $this->sp(10);
        $this->lap([$sp->id . ':' => ['counted' => 12]]);

        $this->ghiSo(StockCount::first());
        $this->ghiSo(StockCount::first())->assertSessionHas('error');

        $this->assertSame(12, $sp->fresh()->stock_quantity);
    }

    #[Test]
    public function quy_cach_giu_ton_rieng(): void
    {
        $sp = $this->sp(100);
        $qc = ProductVariant::create([
            'product_id' => $sp->id, 'name' => 'Chậu sứ', 'sku' => 'KK-QC-1',
            'price' => '150000.00', 'stock_quantity' => 4, 'track_inventory' => true, 'is_active' => true,
        ]);

        $this->lap([$sp->id . ':' . $qc->id => ['counted' => 2]]);
        $this->ghiSo(StockCount::first());

        $this->assertSame(2, $qc->fresh()->stock_quantity);
        $this->assertSame(100, $sp->fresh()->stock_quantity, 'Cột trên sản phẩm có quy cách không phải thứ khách mua.');
    }

    #[Test]
    public function khoa_la_va_hang_khong_theo_doi_ton_bi_bo(): void
    {
        $khongTheoDoi = $this->sp(0, theoDoi: false);

        $this->lap([
            '99999:' => ['counted' => 5],
            $khongTheoDoi->id . ':' => ['counted' => 5],
        ])->assertSessionHas('error');

        $this->assertSame(0, StockCount::count());
    }

    #[Test]
    public function phieu_da_ghi_so_khong_xoa_duoc(): void
    {
        $sp = $this->sp(10);
        $this->lap([$sp->id . ':' => ['counted' => 9]]);
        $p = StockCount::first();
        $this->ghiSo($p);

        $this->actingAs($this->admin())
            ->delete(route('admin.stock-counts.destroy', $p))
            ->assertSessionHas('error');

        $this->assertSame(1, StockCount::count());
    }

    #[Test]
    public function co_nhat_ky_va_hang_doi_viec_nhac_phieu_nhap(): void
    {
        $sp = $this->sp(10);
        $this->lap([$sp->id . ':' => ['counted' => 9]]);

        $this->actingAs($this->admin())->get('/admin/dashboard')
            ->assertSee('phiếu kiểm kê còn nháp, chưa điều chỉnh kho');

        $this->ghiSo(StockCount::first());

        $this->assertTrue(ActivityLog::where('action', 'kho.ghi-so-kiem-ke')->exists());
    }

    #[Test]
    public function cac_trang_mo_duoc_va_khach_thuong_bi_chan(): void
    {
        $sp = $this->sp(10);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.stock-counts.create'))->assertOk()->assertSee($sp->name);
        $this->lap([$sp->id . ':' => ['counted' => 9]], $admin);
        $this->actingAs($admin)->get(route('admin.stock-counts.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.stock-counts.show', StockCount::first()))->assertOk()->assertSee('-1');

        $this->actingAs(User::factory()->create())
            ->get(route('admin.stock-counts.index'))
            ->assertForbidden();
    }
}
