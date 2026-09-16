<?php

namespace Tests\Feature\Gift;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\GiftItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductGift;
use App\Models\User;
use App\Services\Exchange\ExchangeService;
use App\Services\Gift\GiftReturnCalculator;
use App\Services\Refund\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Trả hàng và đổi hàng với dòng quà — theo luật RIÊNG của từng món quà, chỉ điền sẵn. */
class TraDoiQuaTest extends TestCase
{
    use RefreshDatabase;

    private Product $senDa;

    private function donCoQua(int $soMon, int $soQua, ?ProductGift $cauHinh): array
    {
        $this->senDa ??= Product::factory()->for(Category::factory())->price('100000.00')->stock(20)->create(['name' => 'Sen đá A']);
        $tien = bcmul('100000.00', (string) $soMon, 2);

        $don = new Order();
        $don->forceFill([
            'order_number' => 'KT-QUA-' . uniqid(), 'recipient_name' => 'Khách', 'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường', 'shipping_province' => 'Thành phố Hà Nội', 'payment_method' => 'cod',
            'subtotal' => $tien, 'shipping_fee' => '0.00', 'grand_total' => $tien,
            'status' => OrderStatus::Completed, 'payment_status' => PaymentStatus::Paid, 'completed_at' => now()->subDay(),
        ])->save();

        $chinh = new OrderItem();
        $chinh->forceFill([
            'order_id' => $don->id, 'product_id' => $this->senDa->id, 'product_name' => 'Sen đá A',
            'unit_base_price' => '100000.00', 'unit_price' => '100000.00', 'quantity' => $soMon, 'line_total' => $tien, 'discount_amount' => '0.00',
        ])->save();

        $qua = new OrderItem();
        $qua->forceFill([
            'order_id' => $don->id, 'product_id' => null, 'product_name' => 'Túi phân bón nhỏ',
            'unit_base_price' => '15000.00', 'unit_price' => '0.00', 'quantity' => $soQua, 'line_total' => '0.00', 'discount_amount' => '0.00',
            'is_gift' => true, 'gift_item_id' => $cauHinh?->gift_item_id, 'product_gift_id' => $cauHinh?->id, 'parent_item_id' => $chinh->id,
        ])->save();

        return [$don->fresh('items'), $chinh, $qua];
    }

    private function cauHinh(array $luat): ProductGift
    {
        $this->senDa ??= Product::factory()->for(Category::factory())->price('100000.00')->stock(20)->create(['name' => 'Sen đá A']);
        $vat = GiftItem::create(['name' => 'Túi phân bón nhỏ', 'kind' => 'qua_tang', 'stock_quantity' => 50, 'is_active' => true]);

        $pg = new ProductGift(array_merge(['per_quantity' => 1, 'gift_quantity' => 1, 'is_active' => true], $luat));
        $pg->product_id = $this->senDa->id;
        $pg->gift_item_id = $vat->id;
        $pg->save();

        return $pg;
    }

    private function bang(Order $don, OrderItem $qua): array
    {
        return app(GiftReturnCalculator::class)->bangTraKem($don)[$qua->id]['bang'];
    }

    #[Test]
    public function tra_kem_qua_theo_so_mon_con_giu_va_tran_toi_da(): void
    {
        [$don, , $qua] = $this->donCoQua(3, 2, $this->cauHinh(['max_quantity' => 2]));

        $this->assertSame([0, 0, 1, 2], $this->bang($don, $qua));
    }

    #[Test]
    public function luat_khong_thu_hoi_thi_luon_0(): void
    {
        [$don, , $qua] = $this->donCoQua(3, 3, $this->cauHinh(['tra_hang' => 'khong_thu_hoi']));

        $this->assertSame([0, 0, 0, 0], $this->bang($don, $qua));
        $this->assertSame('khong_thu_hoi', app(GiftReturnCalculator::class)->bangTraKem($don)[$qua->id]['quy_tac']);
    }

    #[Test]
    public function cau_hinh_da_bo_thi_chia_theo_ti_le(): void
    {
        [$don, , $qua] = $this->donCoQua(4, 4, null);

        $this->assertSame([0, 1, 2, 3, 4], $this->bang($don, $qua));
    }

    #[Test]
    public function tinh_ca_lan_tra_truoc_cua_mon_chinh_va_cua_qua(): void
    {
        [$don, $chinh, $qua] = $this->donCoQua(3, 3, $this->cauHinh([]));

        app(RefundService::class)->hoan($don, [
            'amount' => 200000, 'reason' => 'tra_hang', 'method' => 'tien_mat',
            'items' => [$chinh->id => ['quantity' => 2, 'restock' => 1], $qua->id => ['quantity' => 1, 'restock' => 1]],
        ]);

        $this->assertSame([1, 2], $this->bang($don->fresh('items'), $qua));
    }

    #[Test]
    public function doi_hang_chan_qua_tru_khi_mon_qua_cho_phep(): void
    {
        $cauHinh = $this->cauHinh([]);
        [$don, $chinh, $qua] = $this->donCoQua(2, 2, $cauHinh);
        $dv = app(ExchangeService::class);

        $this->assertNull($dv->lyDoDongKhongDoiDuoc($chinh));
        $this->assertSame('Quà tặng miễn phí không đổi được sang hàng khác.', $dv->lyDoDongKhongDoiDuoc($qua));

        $cauHinh->update(['cho_doi_hang' => true]);
        $this->assertNull($dv->lyDoDongKhongDoiDuoc($qua->fresh()));

        [, , $quaMoCoi] = $this->donCoQua(1, 1, null);
        $this->assertNotNull($dv->lyDoDongKhongDoiDuoc($quaMoCoi));
    }

    #[Test]
    public function phieu_tra_o_trang_don_co_bang_dien_san_cho_o_qua(): void
    {
        [$don, $chinh, $qua] = $this->donCoQua(3, 2, $this->cauHinh(['max_quantity' => 2]));

        $admin = User::factory()->create();
        $admin->role = UserRole::Admin;
        $admin->save();

        $html = $this->actingAs($admin)->get(route('admin.orders.show', $don))->assertOk()->getContent();

        $this->assertStringContainsString('data-dong-tra="' . $chinh->id . '"', $html);
        $this->assertStringContainsString(
            'data-qua-tra-kem="' . e(json_encode(['cha' => $chinh->id, 'quy_tac' => 'kem_qua', 'bang' => [0, 0, 1, 2]])) . '"',
            $html,
        );
    }
}
