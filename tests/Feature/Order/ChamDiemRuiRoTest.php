<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusEvent;
use App\Models\Product;
use App\Models\User;
use App\Services\Order\OrderRiskScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Điểm rủi ro "bom hàng": chỉ xét COD, không đổ lỗi đơn cửa hàng tự huỷ, bớt điểm khách quen, cộng điểm hoa tươi. */
class ChamDiemRuiRoTest extends TestCase
{
    use RefreshDatabase;

    private int $so = 0;

    private function don(?User $khach, string $pt = 'cod', string $tien = '300000', OrderStatus $tt = OrderStatus::Pending, ?string $email = 'k@vidu.test'): Order
    {
        $don = Order::create([
            'order_number' => 'RR-' . (++$this->so),
            'user_id' => $khach?->id,
            'recipient_name' => 'K',
            'recipient_phone' => '0912345678',
            'recipient_email' => $email,
            'shipping_address' => '1',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => $pt,
            'subtotal' => $tien,
            'discount_total' => '0',
            'shipping_fee' => '0',
            'coupon_discount' => '0',
            'grand_total' => $tien,
        ]);
        $don->forceFill(['status' => $tt->value, 'created_at' => now()->subDays(10 + $this->so)])->save();

        return $don;
    }

    private function suKien(Order $don, OrderStatus $tt, ?User $ai): void
    {
        OrderStatusEvent::create(['order_id' => $don->id, 'status' => $tt->value, 'changed_by' => $ai?->id]);
    }

    private function ma(Order $don): array
    {
        return array_column(app(OrderRiskScorer::class)->score($don)['flags'], 'points', 'code');
    }

    #[Test]
    public function don_cua_hang_tu_huy_khong_tinh_la_loi_cua_khach(): void
    {
        $khach = User::factory()->create();
        $admin = User::factory()->create();

        $cuaHangHuy = $this->don($khach, tt: OrderStatus::Cancelled);
        $this->suKien($cuaHangHuy, OrderStatus::Cancelled, $admin);

        $this->assertArrayNotHasKey('cancelled_before', $this->ma($this->don($khach)));

        $khachTuHuy = $this->don($khach, tt: OrderStatus::Cancelled);
        $this->suKien($khachTuHuy, OrderStatus::Cancelled, $khach);

        $khongNhan = $this->don($khach, tt: OrderStatus::Cancelled);
        $this->suKien($khongNhan, OrderStatus::Shipping, $admin);
        $this->suKien($khongNhan, OrderStatus::Cancelled, $admin);

        $this->assertSame(40, $this->ma($this->don($khach))['cancelled_before'], 'Khách tự huỷ + không nhận khi đang giao');
    }

    #[Test]
    public function don_tra_truoc_khong_co_rui_ro_bom_hang(): void
    {
        $diem = app(OrderRiskScorer::class)->score($this->don(null, 'momo', '5000000', email: null));

        $this->assertSame(0, $diem['score'], 'Đã trả trước: không xét vãng lai, không email, giá trị cao');
    }

    #[Test]
    public function khach_quen_duoc_bot_diem_va_diem_khong_am(): void
    {
        $khach = User::factory()->create();
        $this->don($khach, tt: OrderStatus::Completed);
        $this->don($khach, tt: OrderStatus::Completed);

        $co = $this->ma($this->don($khach, email: null));

        $this->assertSame(-20, $co['trusted']);
        $this->assertSame(0, app(OrderRiskScorer::class)->score($this->don($khach, email: null))['score']);
    }

    #[Test]
    public function don_cod_co_hoa_tuoi_bi_cong_diem(): void
    {
        $hoa = Product::factory()->for(Category::factory())->create(['product_type' => ProductType::Flower]);
        $cay = Product::factory()->for(Category::factory())->create(['product_type' => ProductType::Plant]);

        $donHoa = $this->don(User::factory()->create());
        OrderItem::create(['order_id' => $donHoa->id, 'product_id' => $hoa->id, 'product_name' => 'Hoa', 'unit_base_price' => 300000, 'unit_price' => 300000, 'quantity' => 1, 'line_total' => 300000]);

        $donCay = $this->don(User::factory()->create());
        OrderItem::create(['order_id' => $donCay->id, 'product_id' => $cay->id, 'product_name' => 'Cây', 'unit_base_price' => 300000, 'unit_price' => 300000, 'quantity' => 1, 'line_total' => 300000]);

        $this->assertSame(10, $this->ma($donHoa)['fresh_flower_cod']);
        $this->assertArrayNotHasKey('fresh_flower_cod', $this->ma($donCay));
    }
}
