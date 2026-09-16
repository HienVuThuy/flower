<?php

namespace Tests\Feature\Shipping;

use App\Enums\ShippingStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Khách xem được hàng của mình đang ở đâu. */
class CustomerTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function don(array $ghiDe = []): Order
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->price('300000.00')
            ->stock(10)
            ->create(['weight' => 500]);

        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1]);
        $this->post('/thanh-toan', [
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
        ]);
        $this->post('/thanh-toan/dat-hang');

        $order = Order::latest('id')->firstOrFail();

        if ($ghiDe !== []) {
            $order->forceFill($ghiDe)->save();
        }

        return $order->refresh();
    }

    #[Test]
    public function khach_thay_hang_dang_tren_duong_toi(): void
    {
        $order = $this->don([
            'ghn_order_code' => 'GHN9988',
            'shipping_status' => 'delivering',
        ]);

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertSee('Tình trạng giao hàng')
            ->assertSee('Đang giao đến bạn')
            ->assertSee('sẽ liên hệ với bạn trong hôm nay', escape: false)
            ->assertSee('GHN9988');
    }

    #[Test]
    public function moi_trang_thai_GHN_deu_co_cau_tieng_Viet(): void
    {
        foreach (ShippingStatus::cases() as $trangThai) {
            $this->assertNotSame('', trim($trangThai->label()), $trangThai->value);
            $this->assertNotSame('', trim($trangThai->hint()), $trangThai->value);
            $this->assertNotSame('', trim($trangThai->badge()), $trangThai->value);
        }
    }

    #[Test]
    public function giao_xong_thi_bao_da_giao_thanh_cong(): void
    {
        $order = $this->don([
            'ghn_order_code' => 'GHN1111',
            'shipping_status' => 'delivered',
        ]);

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertSee('Đã giao thành công');
    }

    #[Test]
    public function chua_ban_giao_thi_KHONG_hien_khoi_trong(): void
    {
        $order = $this->don();

        $this->assertNull($order->ghn_order_code);

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertDontSee('Tình trạng giao hàng');
    }

    #[Test]
    public function trang_thai_GHN_chua_biet_thi_lui_ve_cau_chung(): void
    {
        $order = $this->don([
            'ghn_order_code' => 'GHN2222',
            'shipping_status' => 'money_collect_delivering',
        ]);

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertSee('Đang vận chuyển')
            ->assertDontSee('money_collect_delivering');

        $this->assertNull(ShippingStatus::tuGhn('money_collect_delivering'));
    }

    #[Test]
    public function gia_tri_mac_dinh_cua_cot_cung_co_cau_tieng_Viet(): void
    {
        $order = $this->don([
            'ghn_order_code' => 'GHN3333',
            'shipping_status' => 'not_shipped',
        ]);

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertSee('Chưa bàn giao vận chuyển')
            ->assertDontSee('Đang vận chuyển');
    }

    #[Test]
    public function moi_truong_THU_thi_KHONG_hien_nut_tra_cuu(): void
    {
        config(['services.ghn.tracking_url' => null]);

        $order = $this->don([
            'ghn_order_code' => 'L8WA3V',
            'shipping_status' => 'ready_to_pick',
        ]);

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertSee('Tình trạng giao hàng')
            ->assertDontSee('Tra cứu trên Giao Hàng Nhanh')
            ->assertDontSee('donhang.ghn.vn')
            ->assertSee('liên hệ cửa hàng theo số', escape: false);
    }

    #[Test]
    public function cau_hinh_xong_thi_nut_tra_cuu_tro_dung_ma_van_don(): void
    {
        config(['services.ghn.tracking_url' => 'https://donhang.ghn.vn/']);

        $order = $this->don([
            'ghn_order_code' => 'L8WA3V',
            'shipping_status' => 'delivering',
        ]);

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertSee('Tra cứu trên Giao Hàng Nhanh')
            ->assertSee('https://donhang.ghn.vn/?order_code=L8WA3V', escape: false);
    }

    #[Test]
    public function hien_khoang_du_kien_giao_khi_GHN_da_bao(): void
    {
        $order = $this->don([
            'ghn_order_code' => 'GHN7777',
            'shipping_status' => 'delivering',
            'ghn_expected_from' => '2026-09-12 16:59:59',
            'ghn_expected_to' => '2026-09-13 16:59:59',
        ]);

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertSee('Dự kiến giao')
            ->assertSee('12/09', escape: false)
            ->assertSee('13/09/2026', escape: false);
    }

    #[Test]
    public function chua_co_du_kien_giao_thi_KHONG_hua_gi(): void
    {
        $order = $this->don([
            'ghn_order_code' => 'GHN8888',
            'shipping_status' => 'ready_to_pick',
        ]);

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertDontSee('Dự kiến giao');
    }

    #[Test]
    public function khong_tra_cuu_duoc_van_don_cua_nguoi_khac(): void
    {
        $order = $this->don([
            'ghn_order_code' => 'GHN4444',
            'shipping_status' => 'delivering',
        ]);

        $this->flushSession();
        $this->actingAs(User::factory()->create());

        $this->get('/don-hang/' . $order->order_number)->assertForbidden();
    }
}
