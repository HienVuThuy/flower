<?php

namespace Tests\Feature\Admin;

use App\Enums\StockReceiptStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockReceipt;
use App\Models\User;
use App\Services\Inventory\StockReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Phiếu nhập kho. */
class StockReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    private function hang(string $ten, int $ton = 0, bool $theoDoi = true): Product
    {
        return Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->price('100000.00')
            ->create([
                'name' => $ten,
                'status' => 'published',
                'track_inventory' => $theoDoi,
                'stock_quantity' => $ton,
            ]);
    }

    private function quyCach(Product $p, string $ten, int $ton = 0): ProductVariant
    {
        return ProductVariant::create([
            'product_id' => $p->id,
            'name' => $ten,
            'price' => '120000.00',
            'is_active' => true,
            'track_inventory' => true,
            'stock_quantity' => $ton,
        ]);
    }

    private function lapPhieu(array $dong, array $them = []): StockReceipt
    {
        $this->actingAs($this->admin())
            ->post('/admin/nhap-kho', array_merge([
                'received_at' => now()->toDateString(),
                'supplier' => 'Vườn Đà Lạt',
                'items' => $dong,
            ], $them))
            ->assertRedirect();

        return StockReceipt::latest('id')->firstOrFail();
    }

    #[Test]
    public function tao_phieu_KHONG_cong_vao_kho_ngay(): void
    {
        $p = $this->hang('Sen đá', ton: 5);

        $phieu = $this->lapPhieu([
            ['mat_hang' => $p->id . ':', 'quantity' => 50, 'unit_cost' => 30000],
        ]);

        $this->assertSame(StockReceiptStatus::Draft, $phieu->status);
        $this->assertNull($phieu->posted_at);
        $this->assertSame(5, $p->fresh()->stock_quantity, 'Tạo phiếu đã cộng vào kho — lẽ ra phải chờ ghi sổ.');
    }

    #[Test]
    public function ghi_so_CONG_THEM_chu_khong_gan_de(): void
    {
        $p = $this->hang('Sen đá', ton: 5);

        $phieu = $this->lapPhieu([
            ['mat_hang' => $p->id . ':', 'quantity' => 50, 'unit_cost' => 30000],
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/nhap-kho/' . $phieu->id . '/ghi-so')
            ->assertRedirect();

        $this->assertSame(55, $p->fresh()->stock_quantity, 'Đang gán đè thay vì cộng thêm.');
        $this->assertSame(StockReceiptStatus::Posted, $phieu->fresh()->status);
        $this->assertNotNull($phieu->fresh()->posted_at);
    }

    #[Test]
    public function ghi_so_hai_lan_KHONG_cong_kho_hai_lan(): void
    {
        $p = $this->hang('Sen đá', ton: 0);

        $phieu = $this->lapPhieu([
            ['mat_hang' => $p->id . ':', 'quantity' => 10, 'unit_cost' => 1000],
        ]);

        $this->actingAs($this->admin())->post('/admin/nhap-kho/' . $phieu->id . '/ghi-so');
        $this->actingAs($this->admin())
            ->post('/admin/nhap-kho/' . $phieu->id . '/ghi-so')
            ->assertSessionHas('error');

        $this->assertSame(10, $p->fresh()->stock_quantity, 'Kho bị cộng hai lần cho một phiếu.');
    }

    #[Test]
    public function so_luong_AM_tru_bot_kho(): void
    {
        $p = $this->hang('Sen đá', ton: 100);

        $phieu = $this->lapPhieu([
            ['mat_hang' => $p->id . ':', 'quantity' => -30],
        ]);

        $this->actingAs($this->admin())->post('/admin/nhap-kho/' . $phieu->id . '/ghi-so');

        $this->assertSame(70, $p->fresh()->stock_quantity);
    }

    #[Test]
    public function nhap_theo_quy_cach_cong_vao_kho_cua_quy_cach(): void
    {
        $p = $this->hang('Lưỡi hổ', ton: 0);
        $suTrang = $this->quyCach($p, 'Chậu sứ trắng', ton: 2);
        $gomNau = $this->quyCach($p, 'Chậu gốm nâu', ton: 9);

        $phieu = $this->lapPhieu([
            ['mat_hang' => $p->id . ':' . $suTrang->id, 'quantity' => 20, 'unit_cost' => 50000],
        ]);

        $this->actingAs($this->admin())->post('/admin/nhap-kho/' . $phieu->id . '/ghi-so');

        $this->assertSame(22, $suTrang->fresh()->stock_quantity);
        $this->assertSame(9, $gomNau->fresh()->stock_quantity, 'Đã cộng nhầm sang quy cách khác.');
        $this->assertSame(0, $p->fresh()->stock_quantity, 'Đã cộng vào cột tồn của sản phẩm, nơi không ai đọc.');
    }

    #[Test]
    public function hang_KHONG_theo_doi_ton_thi_khong_hien_trong_o_chon(): void
    {
        $this->hang('Có theo dõi');
        $this->hang('Không theo dõi', theoDoi: false);

        $html = $this->actingAs($this->admin())
            ->get('/admin/nhap-kho/create')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Có theo dõi', $html);
        $this->assertStringNotContainsString('Không theo dõi', $html);
    }

    #[Test]
    public function nhap_san_pham_co_quy_cach_ma_khong_chon_quy_cach_thi_bi_tu_choi(): void
    {
        $p = $this->hang('Lưỡi hổ', ton: 0);
        $this->quyCach($p, 'Chậu sứ trắng');

        $phieu = $this->lapPhieu([
            ['mat_hang' => $p->id . ':', 'quantity' => 10],
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/nhap-kho/' . $phieu->id . '/ghi-so')
            ->assertSessionHas('error');

        $this->assertSame(StockReceiptStatus::Draft, $phieu->fresh()->status);
        $this->assertSame(0, $p->fresh()->stock_quantity);
    }

    #[Test]
    public function phieu_da_ghi_so_KHONG_xoa_duoc(): void
    {
        $p = $this->hang('Sen đá');
        $phieu = $this->lapPhieu([['mat_hang' => $p->id . ':', 'quantity' => 5]]);

        $this->actingAs($this->admin())->post('/admin/nhap-kho/' . $phieu->id . '/ghi-so');

        $this->actingAs($this->admin())
            ->delete('/admin/nhap-kho/' . $phieu->id)
            ->assertSessionHas('error');

        $this->assertDatabaseHas('stock_receipts', ['id' => $phieu->id]);
    }

    #[Test]
    public function phieu_con_nhap_thi_xoa_duoc(): void
    {
        $p = $this->hang('Sen đá', ton: 7);
        $phieu = $this->lapPhieu([['mat_hang' => $p->id . ':', 'quantity' => 5]]);

        $this->actingAs($this->admin())
            ->delete('/admin/nhap-kho/' . $phieu->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('stock_receipts', ['id' => $phieu->id]);
        $this->assertSame(7, $p->fresh()->stock_quantity, 'Xoá phiếu nháp không được đụng tới kho.');
    }

    #[Test]
    public function phieu_rong_KHONG_ghi_so_duoc(): void
    {
        $phieu = $this->lapPhieu([
            ['mat_hang' => '', 'quantity' => 0],
        ]);

        $this->assertSame(0, $phieu->items()->count());

        $this->actingAs($this->admin())
            ->post('/admin/nhap-kho/' . $phieu->id . '/ghi-so')
            ->assertSessionHas('error');
    }

    #[Test]
    public function gia_de_trong_luu_NULL_chu_khong_phai_0(): void
    {
        $p = $this->hang('Sen đá');

        $phieu = $this->lapPhieu([
            ['mat_hang' => $p->id . ':', 'quantity' => 10, 'unit_cost' => ''],
        ]);

        $this->assertNull($phieu->items->first()->unit_cost);
        $this->assertTrue($phieu->hasUnpricedItems());
    }

    #[Test]
    public function tong_tien_BO_QUA_dong_chua_dien_gia_chu_khong_tinh_bang_0(): void
    {
        $p = $this->hang('Sen đá');
        $q = $this->hang('Lan hồ điệp');

        $phieu = $this->lapPhieu([
            ['mat_hang' => $p->id . ':', 'quantity' => 10, 'unit_cost' => 20000],
            ['mat_hang' => $q->id . ':', 'quantity' => 5, 'unit_cost' => ''],
        ]);

        $this->assertEqualsWithDelta(200_000, $phieu->totalCost(), 0.01);

        $this->actingAs($this->admin())
            ->get('/admin/nhap-kho/' . $phieu->id)
            ->assertOk()
            ->assertSee('chưa điền giá vốn', escape: false);
    }

    #[Test]
    public function phieu_ghi_lai_nguoi_lap(): void
    {
        $admin = $this->admin();
        $p = $this->hang('Sen đá');

        $this->actingAs($admin)->post('/admin/nhap-kho', [
            'received_at' => now()->toDateString(),
            'items' => [['mat_hang' => $p->id . ':', 'quantity' => 3]],
        ]);

        $phieu = StockReceipt::latest('id')->firstOrFail();

        $this->assertSame($admin->id, $phieu->created_by);
        $this->assertSame($admin->name, $phieu->created_by_name);
    }

    #[Test]
    public function ngay_nhap_o_tuong_lai_bi_chan(): void
    {
        $p = $this->hang('Sen đá');

        $this->actingAs($this->admin())
            ->post('/admin/nhap-kho', [
                'received_at' => now()->addDay()->toDateString(),
                'items' => [['mat_hang' => $p->id . ':', 'quantity' => 5]],
            ])
            ->assertSessionHasErrors('received_at');

        $this->actingAs($this->admin())
            ->post('/admin/nhap-kho', [
                'received_at' => now()->subDays(3)->toDateString(),
                'items' => [['mat_hang' => $p->id . ':', 'quantity' => 5]],
            ])
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function ma_mat_hang_bia_tren_bieu_mau_bi_bo_qua(): void
    {
        $p = $this->hang('Sen đá');

        $phieu = $this->lapPhieu([
            ['mat_hang' => $p->id . ':', 'quantity' => 5],
            ['mat_hang' => '999999:', 'quantity' => 50],
        ]);

        $this->assertSame(1, $phieu->items()->count());
        $this->assertSame($p->id, $phieu->items->first()->product_id);
    }

    #[Test]
    public function khach_va_nguoi_dung_thuong_khong_vao_duoc(): void
    {
        $this->get('/admin/nhap-kho')->assertRedirect();

        $this->actingAs(User::factory()->create())
            ->get('/admin/nhap-kho')
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->post('/admin/nhap-kho', ['received_at' => now()->toDateString(), 'items' => []])
            ->assertForbidden();
    }

    #[Test]
    public function ma_phieu_khong_trung_nhau(): void
    {
        $p = $this->hang('Sen đá');

        $ma = collect(range(1, 5))
            ->map(fn () => $this->lapPhieu([['mat_hang' => $p->id . ':', 'quantity' => 1]])->code)
            ->unique();

        $this->assertCount(5, $ma);
    }
}
