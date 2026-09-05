<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Việc cần làm" ở trang tổng quan quản trị.
 * ============================================================
 * Bản trước, thứ đầu tiên admin nhìn thấy là bốn con số: bao nhiêu danh
 * mục, bao nhiêu sản phẩm, bao nhiêu khách. Không con số nào nói cho họ
 * biết PHẢI LÀM GÌ — chúng gần như không đổi từ ngày này sang ngày khác,
 * và người ta học cách lướt qua.
 *
 * Điều được canh chừng ở đây: KHỐI NÀY PHẢI NÓI ĐÚNG SỰ THẬT. Một cảnh
 * báo hiện sai — hoặc không hiện khi đáng lẽ phải hiện — còn tệ hơn
 * không có cảnh báo, vì admin sẽ tin nó.
 */
class DashboardTodoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    private function order(OrderStatus $status, PaymentStatus $payment): Order
    {
        $order = Order::create([
            'order_number' => 'FP-TEST-'.strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'subtotal' => '300000.00',
            'discount_total' => '0.00',
            'shipping_fee' => '25000.00',
            'coupon_discount' => '0.00',
            'grand_total' => '325000.00',
        ]);

        $order->status = $status;
        $order->payment_status = $payment;
        $order->save();

        return $order;
    }

    private function dashboard()
    {
        return $this->actingAs($this->admin())->get('/admin/dashboard');
    }

    #[Test]
    public function nhac_don_da_huy_ma_khach_da_tra_tien(): void
    {
        /*
         * Việc DUY NHẤT trong bốn mục liên quan tới tiền của người khác.
         * Trước khi có khối này, không chỗ nào hiện nó ra — admin chỉ
         * phát hiện khi tình cờ mở đúng đơn đó.
         */
        $this->order(OrderStatus::Cancelled, PaymentStatus::Paid);

        $this->dashboard()
            ->assertOk()
            ->assertSee('đơn đã huỷ cần hoàn tiền cho khách');
    }

    #[Test]
    public function nhac_don_cho_xac_nhan(): void
    {
        $this->order(OrderStatus::Pending, PaymentStatus::Unpaid);

        $this->dashboard()->assertSee('đơn chờ xác nhận');
    }

    #[Test]
    public function nhac_hang_het_NHUNG_khong_tinh_hang_lam_theo_don(): void
    {
        /*
         * ĐÂY LÀ CHỖ DỄ SAI NHẤT, và cũng là chỗ một cảnh báo sai gây
         * hại nhất: hoa cưới có stock_quantity = 0 nhưng làm theo đơn,
         * không hề hết hàng. Gộp vào thì con số cảnh báo lúc nào cũng
         * khác không, và admin học cách bỏ qua nó — kể cả khi có hàng
         * thật sự hết.
         */
        $cat = Category::factory()->create();
        Product::factory()->for($cat)->madeToOrder()->create();

        $this->dashboard()->assertDontSee('sản phẩm đã hết hàng');

        Product::factory()->for($cat)->stock(0)->create();

        $this->dashboard()->assertSee('sản phẩm đã hết hàng');
    }

    #[Test]
    public function het_viec_thi_noi_het_viec(): void
    {
        // Danh sách toàn "0 đơn chờ xác nhận" là danh sách không ai đọc,
        // và đọc mãi thành quen bỏ qua.
        $this->dashboard()
            ->assertOk()
            ->assertSee('Không có việc nào đang chờ')
            ->assertDontSee('đơn chờ xác nhận');
    }

    #[Test]
    public function moi_dong_dan_thang_toi_danh_sach_da_loc_san(): void
    {
        /*
         * Hiện con số rồi bắt admin tự đi lọc lại là bỏ dở việc giữa
         * chừng. Liên kết phải mang sẵn điều kiện lọc.
         */
        $this->order(OrderStatus::Cancelled, PaymentStatus::Paid);

        $html = $this->dashboard()->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#href="[^"]*admin/orders\?[^"]*status=cancelled[^"]*payment=paid#',
            $html,
            'Dòng "cần hoàn tiền" phải dẫn tới danh sách đã lọc sẵn.',
        );
    }

    #[Test]
    public function khach_thuong_khong_xem_duoc_trang_tong_quan(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/dashboard')
            ->assertForbidden();
    }
}
