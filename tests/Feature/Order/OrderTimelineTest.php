<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Order\OrderException;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Dòng thời gian của đơn hàng. */
class OrderTimelineTest extends TestCase
{
    use RefreshDatabase;

    private function orders(): OrderService
    {
        return app(OrderService::class);
    }

    private function admin(string $ten = 'Nhân viên cửa hàng'): User
    {
        $user = User::factory()->create(['name' => $ten]);
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    private function datHang(): Order
    {
        $product = Product::factory()
            ->for(Category::factory())
            ->price('300000.00')
            ->stock(50)
            ->create();

        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1]);

        $this->post('/thanh-toan', [
            'recipient_name' => 'Nguyễn Văn Kiểm Thử',
            'recipient_phone' => '0912345678',
            'recipient_email' => 'anaorin229@gmail.com',
            'shipping_address' => '12 Đường Thử Nghiệm',
            'shipping_province' => 'Thành phố Hồ Chí Minh',
            'payment_method' => 'cod',
            'address_id' => '',
        ]);

        $this->post('/thanh-toan/dat-hang')->assertRedirect();

        return Order::latest('id')->firstOrFail();
    }

    #[Test]
    public function don_vua_dat_da_co_moc_dau_tien(): void
    {
        $this->actingAs(User::factory()->create());

        $order = $this->datHang();

        $this->assertCount(1, $order->statusEvents);
        $this->assertSame(OrderStatus::Pending, $order->statusEvents->first()->status);
    }

    #[Test]
    public function moi_lan_doi_trang_thai_them_dung_mot_moc(): void
    {
        $this->actingAs(User::factory()->create());
        $order = $this->datHang();

        $this->actingAs($this->admin());

        foreach ([OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Shipping] as $buoc) {
            $this->orders()->changeStatus($order->fresh(), $buoc);
        }

        $this->assertSame(
            ['pending', 'confirmed', 'preparing', 'shipping'],
            $order->fresh()->statusEvents->pluck('status.value')->all(),
            'Dòng thời gian phải giữ đủ mọi bước, theo đúng thứ tự.',
        );
    }

    #[Test]
    public function moc_ghi_lai_ai_da_doi_trang_thai(): void
    {
        $this->actingAs(User::factory()->create());
        $order = $this->datHang();

        $this->actingAs($this->admin('Lê Văn Kho'));
        $this->orders()->changeStatus($order->fresh(), OrderStatus::Confirmed);

        $moc = $order->fresh()->statusEvents->last();

        $this->assertSame('Lê Văn Kho', $moc->actorLabel());
    }

    #[Test]
    public function moc_dau_tien_khong_gan_ten_ai(): void
    {
        $this->actingAs(User::factory()->create());

        $moc = $this->datHang()->statusEvents->first();

        $this->assertNull($moc->changed_by);
        $this->assertSame('Hệ thống', $moc->actorLabel());
    }

    #[Test]
    public function ly_do_huy_duoc_luu_vao_dung_moc_huy(): void
    {
        $this->actingAs(User::factory()->create());
        $order = $this->datHang();

        $this->actingAs($this->admin());
        $this->orders()->changeStatus($order->fresh(), OrderStatus::Cancelled, 'Khách đổi ý');

        $moc = $order->fresh()->statusEvents->last();

        $this->assertSame(OrderStatus::Cancelled, $moc->status);
        $this->assertSame('Khách đổi ý', $moc->note);
    }

    #[Test]
    public function doi_trang_thai_that_bai_thi_KHONG_de_lai_moc(): void
    {
        $this->actingAs(User::factory()->create());
        $order = $this->datHang();

        $this->actingAs($this->admin());
        $this->orders()->changeStatus($order->fresh(), OrderStatus::Cancelled, 'Khách đổi ý');

        $soMoc = $order->fresh()->statusEvents->count();

        try {
            $this->orders()->changeStatus($order->fresh(), OrderStatus::Confirmed);
            $this->fail('Đơn đã huỷ thì không xác nhận lại được.');
        } catch (OrderException) {
        }

        $this->assertSame($soMoc, $order->fresh()->statusEvents->count(),
            'Thao tác bị từ chối không được để lại mốc nào.');
    }

    #[Test]
    public function khach_xem_duoc_dong_thoi_gian_don_cua_minh(): void
    {
        $khach = User::factory()->create();
        $this->actingAs($khach);

        $order = $this->datHang();

        $this->actingAs($this->admin('Lê Văn Kho'));
        $this->orders()->changeStatus($order->fresh(), OrderStatus::Confirmed);

        $this->actingAs($khach)
            ->get('/don-hang/'.$order->order_number)
            ->assertOk()
            ->assertSee('order-timeline', false)
            ->assertSee('Chờ xác nhận')
            ->assertSee('Đã xác nhận');
    }

    #[Test]
    public function trang_cua_khach_KHONG_hien_ten_nhan_vien(): void
    {
        $khach = User::factory()->create();
        $this->actingAs($khach);
        $order = $this->datHang();

        $this->actingAs($this->admin('Lê Văn Kho'));
        $this->orders()->changeStatus($order->fresh(), OrderStatus::Confirmed);

        $this->actingAs($khach)
            ->get('/don-hang/'.$order->order_number)
            ->assertOk()
            ->assertDontSee('Lê Văn Kho');
    }

    #[Test]
    public function trang_quan_tri_CO_hien_ten_nhan_vien(): void
    {
        $this->actingAs(User::factory()->create());
        $order = $this->datHang();

        $admin = $this->admin('Lê Văn Kho');
        $this->actingAs($admin);
        $this->orders()->changeStatus($order->fresh(), OrderStatus::Confirmed);

        $this->get('/admin/orders/'.$order->order_number)
            ->assertOk()
            ->assertSee('Lê Văn Kho');
    }
}
