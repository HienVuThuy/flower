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

/**
 * Khách xem được hàng của mình đang ở đâu.
 * ============================================================
 * VẤN ĐỀ ĐÃ SỬA: trang đơn chỉ hiện `OrderStatus` — cửa hàng đang làm gì
 * với đơn. Không có gì nói KIỆN HÀNG đang ở đâu.
 *
 * Một đơn "Đang giao" nằm im ba ngày trông y hệt nhau ở ngày đầu và ngày
 * thứ ba: không biết hàng đã rời kho chưa, hôm nay có ai mang tới không.
 * Người đợi hàng gọi điện hỏi cửa hàng, và cửa hàng cũng phải đi hỏi GHN
 * — dù `orders.shipping_status` đã có sẵn con số đó từ lâu.
 *
 * Bốn bất biến:
 *
 *   1. Có vận đơn thì hiện tình trạng, bằng tiếng Việt.
 *   2. Chưa có vận đơn thì KHÔNG hiện khối trống.
 *   3. Mã lạ của GHN không được làm gãy trang.
 *   4. Không xem được đơn của người khác.
 */
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

    /* ================= 1. CÓ VẬN ĐƠN THÌ NÓI RÕ ================= */

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
            // Câu trả lời cho "vậy giờ tôi phải làm gì".
            ->assertSee('sẽ liên hệ với bạn trong hôm nay', escape: false)
            ->assertSee('GHN9988');
    }

    #[Test]
    public function moi_trang_thai_GHN_deu_co_cau_tieng_Viet(): void
    {
        /*
         * Duyệt CẢ enum chứ không kiểm vài mã tiêu biểu: thêm một case
         * mà quên viết nhãn thì match() ném lỗi ngay tại trang đơn của
         * khách — và chỉ đúng khách gặp trạng thái đó mới thấy.
         */
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

    /* ================= 2. CHƯA CÓ VẬN ĐƠN THÌ IM LẶNG ================= */

    #[Test]
    public function chua_ban_giao_thi_KHONG_hien_khoi_trong(): void
    {
        /*
         * Một khối "chưa có thông tin vận chuyển" chỉ làm khách tưởng
         * đơn bị bỏ quên. Chưa bàn giao thì thật sự chưa có gì để nói —
         * dòng thời gian ở trên đã cho biết đơn đang ở bước nào.
         */
        $order = $this->don();

        $this->assertNull($order->ghn_order_code);

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertDontSee('Tình trạng giao hàng');
    }

    /* ================= 3. MÃ LẠ KHÔNG LÀM GÃY TRANG ================= */

    #[Test]
    public function trang_thai_GHN_chua_biet_thi_lui_ve_cau_chung(): void
    {
        /*
         * GHN thêm trạng thái mới bất cứ lúc nào. Nếu cột được cast sang
         * enum thì một chuỗi lạ ném lỗi ngay giữa trang đơn của khách —
         * đó là lý do cột vẫn là chuỗi và việc dịch nằm ở tầng hiển thị.
         */
        $order = $this->don([
            'ghn_order_code' => 'GHN2222',
            'shipping_status' => 'money_collect_delivering',
        ]);

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertSee('Đang vận chuyển')
            // KHÔNG in nguyên mã tiếng Anh của GHN ra cho khách đọc.
            ->assertDontSee('money_collect_delivering');

        $this->assertNull(ShippingStatus::tuGhn('money_collect_delivering'));
    }

    #[Test]
    public function gia_tri_mac_dinh_cua_cot_cung_co_cau_tieng_Viet(): void
    {
        /*
         * `not_shipped` là giá trị MẶC ĐỊNH của cột, không phải mã của
         * GHN — cửa hàng chưa bàn giao cho ai. Bỏ sót nó thì đơn ở trạng
         * thái đó rơi vào câu lùi "Đang vận chuyển", nói ngược hẳn sự
         * thật: hàng vẫn đang nằm ở cửa hàng.
         */
        $order = $this->don([
            'ghn_order_code' => 'GHN3333',
            'shipping_status' => 'not_shipped',
        ]);

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertSee('Chưa bàn giao vận chuyển')
            ->assertDontSee('Đang vận chuyển');
    }

    /* ================= 4. KHÔNG XEM ĐƯỢC ĐƠN NGƯỜI KHÁC ================= */

    #[Test]
    public function khong_tra_cuu_duoc_van_don_cua_nguoi_khac(): void
    {
        /*
         * Mã vận đơn tra được trên trang GHN, ở đó có tên và số điện
         * thoại người nhận. Để lộ nó là để lộ địa chỉ giao hàng của
         * người lạ.
         */
        $order = $this->don([
            'ghn_order_code' => 'GHN4444',
            'shipping_status' => 'delivering',
        ]);

        $this->flushSession();
        $this->actingAs(User::factory()->create());

        $this->get('/don-hang/' . $order->order_number)->assertForbidden();
    }
}
