<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderStatusMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Thư nào gửi ở trạng thái nào.
 * ============================================================
 * LỖI GỐC: thư "Xác nhận đơn hàng" được gửi NGAY lúc khách bấm đặt, khi
 * đơn còn ở trạng thái "Chờ xác nhận" — tức là chưa ai ở cửa hàng nhìn
 * thấy nó, chưa ai kiểm hàng còn hay hết, chưa ai xem địa chỉ có ship
 * tới được không.
 *
 * Hậu quả khi cửa hàng phải từ chối sau đó: khách đã cầm trong tay một
 * lá thư tên là "Xác nhận đơn hàng" và hiểu là đã chốt xong.
 *
 * "Chờ xác nhận" và "Đã xác nhận" là HAI TRẠNG THÁI KHÁC NHAU. Các bài
 * dưới đây canh cho sáu trạng thái không bị trộn lẫn.
 */
class OrderMailTimingTest extends TestCase
{
    use RefreshDatabase;

    private function orders(): OrderService
    {
        return app(OrderService::class);
    }

    /** Đặt một đơn qua đúng đường khách vẫn đi. */
    private function datHang(): Order
    {
        $product = Product::factory()
            ->for(Category::factory())
            ->price('300000.00')
            ->stock(20)
            ->create();

        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1]);

        $this->post('/thanh-toan', [
            'recipient_name' => 'Nguyễn Văn Kiểm Thử',
            'recipient_phone' => '0912345678',
            'recipient_email' => 'anaorin229@gmail.com',
            'shipping_address' => '12 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'address_id' => '',
        ]);

        $this->post('/thanh-toan/dat-hang')->assertRedirect();

        return Order::latest('id')->firstOrFail();
    }

    #[Test]
    public function dat_hang_xong_KHONG_gui_thu_nao(): void
    {
        Mail::fake();

        $this->actingAs(User::factory()->create());

        $order = $this->datHang();

        $this->assertSame(OrderStatus::Pending, $order->status,
            'Đơn mới phải ở trạng thái "Chờ xác nhận".');

        Mail::assertNothingSent();
    }

    #[Test]
    public function chi_khi_admin_chon_da_xac_nhan_thi_thu_moi_di(): void
    {
        $this->actingAs(User::factory()->create());

        $order = $this->datHang();

        Mail::fake();

        $this->orders()->changeStatus($order, OrderStatus::Confirmed);

        /*
         * Đây là lúc khách cần một BIÊN NHẬN đầy đủ để đối chiếu: đủ mặt
         * hàng, đủ số lượng, đủ tổng tiền, đủ địa chỉ giao.
         */
        Mail::assertSent(OrderConfirmationMail::class);
    }

    #[Test]
    public function dang_chuan_bi_khong_gui_thu(): void
    {
        /*
         * "Đang chuẩn bị" là bước NỘI BỘ của cửa hàng — gói hàng, cắt
         * hoa. Với khách thì không có gì mới so với "đã xác nhận".
         *
         * Gửi thư cho mọi bước nhỏ là cách nhanh nhất khiến người ta lọc
         * thẳng thư của cửa hàng vào thùng rác, và khi đó thư THẬT SỰ
         * quan trọng cũng chung số phận.
         */
        $this->actingAs(User::factory()->create());

        $order = $this->datHang();
        $this->orders()->changeStatus($order, OrderStatus::Confirmed);

        Mail::fake();

        $this->orders()->changeStatus($order->fresh(), OrderStatus::Preparing);

        Mail::assertNothingSent();
    }

    #[Test]
    public function ba_trang_thai_sau_gui_thu_cap_nhat_ngan(): void
    {
        // Đang giao / Đã giao / Đã huỷ là tin cập nhật, không cần lặp
        // lại toàn bộ biên nhận.
        $this->actingAs(User::factory()->create());

        $order = $this->datHang();
        $this->orders()->changeStatus($order, OrderStatus::Confirmed);
        $this->orders()->changeStatus($order->fresh(), OrderStatus::Preparing);

        Mail::fake();
        $this->orders()->changeStatus($order->fresh(), OrderStatus::Shipping);
        Mail::assertSent(OrderStatusMail::class);
        Mail::assertNotSent(OrderConfirmationMail::class);

        Mail::fake();
        $this->orders()->changeStatus($order->fresh(), OrderStatus::Completed);
        Mail::assertSent(OrderStatusMail::class);
    }

    #[Test]
    public function huy_don_van_bao_cho_khach(): void
    {
        /*
         * Trạng thái DUY NHẤT mà im lặng là không chấp nhận được: khách
         * đang chờ hàng, và nếu không ai báo thì họ chờ mãi.
         */
        $this->actingAs(User::factory()->create());

        $order = $this->datHang();

        Mail::fake();

        $this->orders()->changeStatus($order, OrderStatus::Cancelled, 'Hết hàng');

        Mail::assertSent(OrderStatusMail::class);
    }

    #[Test]
    public function man_hinh_sau_khi_dat_khong_hua_mot_la_thu_da_gui(): void
    {
        /*
         * Bản trước báo "Xác nhận đơn đã được gửi tới ..." ngay tại màn
         * hình này. Nay thư chưa đi, nên câu đó thành lời hứa sai: khách
         * mở hộp thư tìm một lá thư chưa tồn tại rồi kết luận hệ thống
         * hỏng hoặc đơn không vào.
         */
        $this->actingAs(User::factory()->create());

        $order = $this->datHang();

        $this->get('/don-hang/'.$order->getRouteKey())
            ->assertOk()
            ->assertDontSee('Xác nhận đơn đã được gửi');
    }
}
