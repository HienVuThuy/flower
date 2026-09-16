<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Services\Time\Gio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Giờ hiển thị và giờ nhập vào, đều theo giờ Việt Nam. */
class GioHienThiTest extends TestCase
{
    use RefreshDatabase;

    private const UTC = '2026-09-10 11:42:37';

    private const VN_GIO = '18:42';

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    private function donCuaKhach(User $khach): Order
    {
        $product = Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->price('250000.00')
            ->stock(10)
            ->create(['name' => 'Cây kiểm thử giờ', 'weight' => 500]);

        $this->actingAs($khach);
        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1]);
        $this->post('/thanh-toan', [
            'recipient_name' => 'Khách thử giờ',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
        ]);
        $this->post('/thanh-toan/dat-hang');

        $order = Order::latest('id')->firstOrFail();

        $order->forceFill(['created_at' => self::UTC])->save();

        return $order->refresh();
    }

    #[Test]
    public function trang_don_cua_khach_hien_gio_Viet_Nam_chu_khong_phai_gio_luu(): void
    {
        $khach = User::factory()->create();
        $don = $this->donCuaKhach($khach);

        $html = $this->actingAs($khach)
            ->get('/don-hang')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(self::VN_GIO, $html, 'Trang đơn không hiện giờ Hà Nội');

        $this->assertStringNotContainsString('11:42', $html, 'Trang đơn vẫn còn in giờ UTC');

        $this->assertStringContainsString('+07:00', $html, 'Thẻ <time> phải mang mốc kèm múi giờ');

        $this->assertStringContainsString($don->order_number, $html);
    }

    #[Test]
    public function trang_don_ben_quan_tri_cung_hien_dung_gio(): void
    {
        $don = $this->donCuaKhach(User::factory()->create());

        $html = $this->actingAs($this->admin())
            ->get('/admin/orders/' . $don->order_number)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(self::VN_GIO, $html);
        $this->assertStringNotContainsString('11:42', $html);
    }

    #[Test]
    public function the_time_mang_moc_day_du_va_null_thi_in_phan_than(): void
    {
        $co = trim(Blade::render(
            '<x-site.time :at="$at" />',
            ['at' => Carbon::parse(self::UTC)],
        ));

        $this->assertStringContainsString('datetime="2026-09-10T18:42:37+07:00"', $co);
        $this->assertStringContainsString('>' . self::VN_GIO . ' 10/09/2026<', $co);

        $khong = trim(Blade::render('<x-site.time :at="null">chưa giao</x-site.time>'));

        $this->assertSame('chưa giao', $khong);
        $this->assertStringNotContainsString('<time', $khong);
    }

    #[Test]
    public function khuyen_mai_hen_8h_sang_thi_luu_dung_moc_8h_sang_gio_Ha_Noi(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/promotions', [
                'name' => 'Khuyến mại thử giờ',
                'slug' => 'khuyen-mai-thu-gio',
                'type' => 'percent',
                'discount_value' => 10,
                'status' => 'scheduled',
                'priority' => 0,
                'starts_at' => '2026-09-10T08:00',
                'ends_at' => '2026-09-20T23:30',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $km = Promotion::where('slug', 'khuyen-mai-thu-gio')->firstOrFail();

        $this->assertSame('2026-09-10 01:00:00', $km->getRawOriginal('starts_at'));
        $this->assertSame('2026-09-20 16:30:00', $km->getRawOriginal('ends_at'));
    }

    #[Test]
    public function mo_lai_bieu_mau_thi_o_hien_dung_con_so_da_go(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/promotions', [
            'name' => 'Khuyến mại vòng tròn',
            'slug' => 'khuyen-mai-vong-tron',
            'type' => 'percent',
            'discount_value' => 10,
            'status' => 'scheduled',
            'priority' => 0,
            'starts_at' => '2026-09-10T08:00',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $km = Promotion::where('slug', 'khuyen-mai-vong-tron')->firstOrFail();

        $html = $this->actingAs($admin)
            ->get('/admin/promotions/' . $km->id . '/edit')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('value="2026-09-10T08:00"', $html);
        $this->assertStringNotContainsString('value="2026-09-10T01:00"', $html);
    }

    #[Test]
    public function ma_giam_gia_cung_di_qua_cung_mot_duong(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/coupons', [
            'code' => 'GIOTHU',
            'name' => 'Mã kiểm thử giờ',
            'type' => 'percent',
            'value' => 10,
            'status' => 'scheduled',
            'starts_at' => '2026-09-10T08:00',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $ma = Coupon::where('code', 'GIOTHU')->firstOrFail();

        $this->assertSame('2026-09-10 01:00:00', $ma->getRawOriginal('starts_at'));
    }

    #[Test]
    public function truoc_7h_sang_thi_hom_nay_van_la_hom_nay(): void
    {
        $this->travelTo(Carbon::parse('2026-09-10 22:30:00', 'UTC'));

        $this->assertSame('2026-09-10', now()->format('Y-m-d'));
        $this->assertSame('2026-09-11', Gio::choONgay(now()));

        $this->travelBack();
    }

    #[Test]
    public function bao_cao_va_trang_hien_thi_dung_chung_mot_mui_gio(): void
    {
        $this->assertSame(
            Gio::mui(),
            \App\Services\Analytics\KhoangThoiGian::muiGio(),
        );

        $this->assertSame('Asia/Ho_Chi_Minh', Gio::mui());
        $this->assertSame('UTC', Gio::muiLuu());
    }

    #[Test]
    public function o_nhap_de_trong_thi_khong_bi_dat_moc(): void
    {
        $this->assertSame([], Gio::doiONhap(['starts_at' => '', 'ends_at' => null], 'starts_at', 'ends_at'));
        $this->assertSame([], Gio::doiONhap([], 'starts_at'));

        $this->assertNull(Gio::hien(null));
        $this->assertNull(Gio::choO(null));
    }

    #[Test]
    public function o_gio_gui_len_kieu_la_thi_bo_qua_chu_khong_vo(): void
    {
        $this->assertSame([], Gio::doiONhap(['starts_at' => ['2026-09-10T08:00']], 'starts_at'));
        $this->assertSame([], Gio::doiONhap(['starts_at' => 123], 'starts_at'));
        $this->assertSame([], Gio::doiONhap(['starts_at' => true], 'starts_at'));

        $res = $this->actingAs($this->admin())->post('/admin/promotions', [
            'name' => 'Khuyến mại kiểu lạ',
            'slug' => 'khuyen-mai-kieu-la',
            'type' => 'percent',
            'discount_value' => 10,
            'status' => 'scheduled',
            'priority' => 0,
            'starts_at' => ['2026-09-10T08:00'],
        ]);

        $this->assertLessThan(500, $res->getStatusCode(), 'Mảng gửi vào ô giờ làm vỡ trang');
    }
}
