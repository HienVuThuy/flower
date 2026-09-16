<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\StockReceiptKind;
use App\Enums\StockReceiptStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockReceipt;
use App\Models\User;
use App\Services\Analytics\KhoangThoiGian;
use App\Services\Analytics\ProfitReport;
use App\Services\Inventory\StockReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Tồn đầu kỳ: hàng đã nằm trên kệ trước khi có hệ thống. */
class TonDauKyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    private function sp(string $ten, int $ton = 10, string $gia = '200000.00'): Product
    {
        return Product::factory()
            ->for(Category::factory())
            ->price($gia)
            ->stock($ton)
            ->create(['name' => $ten]);
    }

    private function khai(array $items, array $ghiDe = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())->post('/admin/ton-dau-ky', array_merge([
            'received_at' => now()->subDays(30)->toDateString(),
            'items' => $items,
        ], $ghiDe));
    }

    #[Test]
    public function ghi_so_phieu_ton_dau_ky_KHONG_cong_vao_kho(): void
    {
        $sp = $this->sp('Chậu sứ', ton: 10);

        $this->khai([$sp->id => ['quantity' => 10, 'unit_cost' => 120000]])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $phieu = StockReceipt::firstOrFail();

        $this->assertSame(StockReceiptKind::TonDauKy, $phieu->kind);
        $this->assertSame(10, (int) $sp->fresh()->stock_quantity);

        app(StockReceiptService::class)->ghiSo($phieu);

        $this->assertSame(StockReceiptStatus::Posted, $phieu->fresh()->status);
        $this->assertSame(
            10,
            (int) $sp->fresh()->stock_quantity,
            'Phiếu tồn đầu kỳ đã cộng vào kho — tồn của cả cửa hàng bị nhân đôi',
        );
    }

    #[Test]
    public function phieu_nhap_thuong_thi_VAN_cong_vao_kho(): void
    {
        $sp = $this->sp('Chậu sứ', ton: 10);

        $this->actingAs($this->admin())->post('/admin/nhap-kho', [
            'received_at' => now()->toDateString(),
            'items' => [['mat_hang' => (string) $sp->id, 'quantity' => 5, 'unit_cost' => 120000]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        app(StockReceiptService::class)->ghiSo(StockReceipt::firstOrFail());

        $this->assertSame(15, (int) $sp->fresh()->stock_quantity);
    }

    #[Test]
    public function khai_ton_dau_ky_xong_thi_doanh_thu_moi_co_gia_von(): void
    {
        $sp = $this->sp('Chậu sứ', ton: 10, gia: '200000.00');

        $this->donDaGiao($sp, soLuong: 2, donGia: '200000.00', luc: now()->subDays(5));

        $bao = fn () => app(ProfitReport::class)
            ->trong(new KhoangThoiGian(KhoangThoiGian::nuaDemTruoc(29), null))
            ->baoCao();

        $truoc = $bao();
        $this->assertSame('0.00', $truoc['doanh_thu_co_gia_von']);

        $this->khai(
            [$sp->id => ['quantity' => 10, 'unit_cost' => 120000]],
            ['received_at' => now()->subDays(20)->toDateString()],
        )->assertRedirect();

        app(StockReceiptService::class)->ghiSo(StockReceipt::firstOrFail());

        $sau = $bao();

        $this->assertNotSame('0.00', $sau['doanh_thu_co_gia_von'], 'Khai xong mà vẫn không có giá vốn');
        $this->assertSame('240000.00', $sau['gia_von'], 'Giá vốn phải là 2 cái × 120.000đ');
    }

    #[Test]
    public function ngay_chot_SAU_ngay_ban_thi_don_do_van_khong_co_gia_von(): void
    {
        $sp = $this->sp('Chậu sứ', ton: 10);

        $this->donDaGiao($sp, soLuong: 2, donGia: '200000.00', luc: now()->subDays(20));

        $this->khai(
            [$sp->id => ['quantity' => 10, 'unit_cost' => 120000]],
            ['received_at' => now()->subDays(5)->toDateString()],
        )->assertRedirect();

        app(StockReceiptService::class)->ghiSo(StockReceipt::firstOrFail());

        $bao = app(ProfitReport::class)
            ->trong(new KhoangThoiGian(KhoangThoiGian::nuaDemTruoc(29), null))
            ->baoCao();

        $this->assertSame('0.00', $bao['doanh_thu_co_gia_von']);
    }

    #[Test]
    public function de_trong_gia_von_thi_dong_do_bi_bo_qua_chu_khong_ghi_0(): void
    {
        $a = $this->sp('Có nhớ giá');
        $b = $this->sp('Không nhớ giá');

        $this->khai([
            $a->id => ['quantity' => 10, 'unit_cost' => 120000],
            $b->id => ['quantity' => 10, 'unit_cost' => null],
        ])->assertRedirect();

        $phieu = StockReceipt::firstOrFail();

        $this->assertSame(1, $phieu->items()->count());
        $this->assertSame($a->id, $phieu->items()->first()->product_id);
    }

    #[Test]
    public function gia_von_bang_0_bi_tu_choi(): void
    {
        $sp = $this->sp('Chậu sứ');

        $this->khai([$sp->id => ['quantity' => 10, 'unit_cost' => 0]])
            ->assertSessionHasErrors('items.' . $sp->id . '.unit_cost');

        $this->assertSame(0, StockReceipt::count());
    }

    #[Test]
    public function khong_dong_nao_hop_le_thi_bao_loi_chu_khong_lap_phieu_rong(): void
    {
        $sp = $this->sp('Chậu sứ');

        $this->khai([$sp->id => ['quantity' => 0, 'unit_cost' => null]])
            ->assertSessionHas('error');

        $this->assertSame(0, StockReceipt::count());
    }

    #[Test]
    public function mat_hang_da_co_gia_von_khong_con_hien_o_trang_khai(): void
    {
        $daKhai = $this->sp('Đã khai rồi');
        $chuaKhai = $this->sp('Chưa khai');

        $this->khai([$daKhai->id => ['quantity' => 10, 'unit_cost' => 120000]])->assertRedirect();

        $html = $this->actingAs($this->admin())
            ->get('/admin/ton-dau-ky')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('items[' . $chuaKhai->id . ']', $html);
        $this->assertStringNotContainsString('items[' . $daKhai->id . ']', $html);
    }

    #[Test]
    public function mat_hang_khong_theo_doi_ton_khong_hien_o_trang_khai(): void
    {
        $hoa = Product::factory()
            ->for(Category::factory())
            ->create(['name' => 'Hồng đỏ', 'track_inventory' => false, 'stock_quantity' => 0]);

        $html = $this->actingAs($this->admin())
            ->get('/admin/ton-dau-ky')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('items[' . $hoa->id . ']', $html);
    }

    private function donDaGiao(Product $sp, int $soLuong, string $donGia, \Illuminate\Support\Carbon $luc): Order
    {
        $tien = bcmul($donGia, (string) $soLuong, 2);

        $don = new Order();
        $don->forceFill([
            'order_number' => 'KT-' . uniqid(),
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'subtotal' => $tien,
            'grand_total' => $tien,
            'status' => OrderStatus::Completed,
            'created_at' => $luc,
            'updated_at' => $luc,
        ])->save();

        (new OrderItem())->forceFill([
            'order_id' => $don->id,
            'product_id' => $sp->id,
            'product_name' => $sp->name,
            'unit_base_price' => $donGia,
            'unit_price' => $donGia,
            'quantity' => $soLuong,
            'line_total' => $tien,
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'created_at' => $luc,
            'updated_at' => $luc,
        ])->save();

        return $don;
    }
}
