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

/**
 * Giờ hiển thị và giờ nhập vào, đều theo giờ Việt Nam.
 * ============================================================
 * LỖI ĐÃ SỬA — đo trên dữ liệu thật trước khi sửa: một đơn đặt lúc
 * **18:42** giờ Hà Nội hiện ra **11:42** ở trang đơn của khách, trang
 * quản trị và email xác nhận. Sớm đúng 7 tiếng, ở mọi chỗ, không có gì
 * báo. Nguyên nhân: ứng dụng lưu UTC, còn Blade in thẳng
 * `->format(...)`.
 *
 * Toàn bộ 803 bài kiểm thử vẫn xanh cả trước lẫn sau khi sửa — vì không
 * bài nào đo giờ hiển thị. Đó chính là lý do tệp này tồn tại.
 *
 * ============================================================
 * HAI CHIỀU, KHÔNG PHẢI MỘT.
 *
 * Chỉ sửa chiều hiển thị là làm hỏng thêm: ô `datetime-local` gửi lên
 * giờ trên đồng hồ người gõ, mà chuỗi đó đang được cất thẳng vào cột như
 * thể nó là UTC — khuyến mại hẹn 8h sáng **chạy lúc 15h**. Nên các bài ở
 * đây canh cả chiều đọc ra lẫn chiều ghi vào, và canh cả vòng tròn: gõ
 * vào rồi mở lại biểu mẫu phải thấy đúng con số đã gõ.
 */
class GioHienThiTest extends TestCase
{
    use RefreshDatabase;

    /** 11:42 UTC = 18:42 giờ Hà Nội. Chênh 7 tiếng là đủ để lỗi lộ ra. */
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

        // Đặt mốc cố định, bỏ qua $fillable — timestamps không nằm trong đó.
        $order->forceFill(['created_at' => self::UTC])->save();

        return $order->refresh();
    }

    /* ================= CHIỀU ĐỌC RA ================= */

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

        /*
         * Và KHÔNG được còn giờ UTC ở đó. Không có khẳng định này thì một
         * trang in cả hai giờ vẫn qua bài — trong khi đó đúng là lỗi.
         */
        $this->assertStringNotContainsString('11:42', $html, 'Trang đơn vẫn còn in giờ UTC');

        $this->assertStringContainsString('+07:00', $html, 'Thẻ <time> phải mang mốc kèm múi giờ');

        // Cho chắc là đơn đúng chứ không phải trùng chuỗi ở đâu đó.
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

        /*
         * null nghĩa là CHƯA CÓ MỐC NÀO — chưa giao, chưa hết hạn, chưa
         * đăng. In giờ hiện tại vào đó là bịa ra một sự kiện chưa xảy ra.
         */
        $khong = trim(Blade::render('<x-site.time :at="null">chưa giao</x-site.time>'));

        $this->assertSame('chưa giao', $khong);
        $this->assertStringNotContainsString('<time', $khong);
    }

    /* ================= CHIỀU GHI VÀO ================= */

    #[Test]
    public function khuyen_mai_hen_8h_sang_thi_luu_dung_moc_8h_sang_gio_Ha_Noi(): void
    {
        /*
         * ĐÂY LÀ LỖI NẶNG NHẤT trong hai chiều: nó không chỉ hiện sai mà
         * CHẠY SAI. Chương trình hẹn mở 8h sáng, cất thẳng chuỗi vào cột
         * thì `now()` (UTC) chỉ vượt qua nó lúc 15h giờ Hà Nội — khuyến
         * mại nằm im suốt buổi sáng.
         */
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

        // 08:00 Hà Nội = 01:00 UTC.
        $this->assertSame('2026-09-10 01:00:00', $km->getRawOriginal('starts_at'));
        $this->assertSame('2026-09-20 16:30:00', $km->getRawOriginal('ends_at'));
    }

    #[Test]
    public function mo_lai_bieu_mau_thi_o_hien_dung_con_so_da_go(): void
    {
        /*
         * Vòng tròn khép kín. Sửa một đầu mà quên đầu kia thì cơ sở dữ
         * liệu đúng lên nhưng biểu mẫu hiện lệch 7 tiếng, và người sửa
         * lần sau vô tình đẩy mốc đi thêm 7 tiếng nữa mỗi lần bấm Lưu.
         */
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

    /* ================= HÔM NAY LÀ NGÀY NÀO ================= */

    #[Test]
    public function truoc_7h_sang_thi_hom_nay_van_la_hom_nay(): void
    {
        /*
         * LỖI NGẦM NHẤT CỦA CẢ NHÓM NÀY.
         *
         * `now()->format('Y-m-d')` là ngày theo giờ UTC. Từ 0h tới 7h
         * sáng giờ Hà Nội, UTC vẫn đang ở NGÀY HÔM QUA — nên ô chọn ngày
         * mặc định lùi một ngày, và `max` chặn mất chính ngày hôm nay.
         * Người dùng mở nhật ký lúc 6h sáng thì không ghi được cho hôm
         * nay, và không có thông báo nào giải thích vì sao.
         */
        $this->travelTo(Carbon::parse('2026-09-10 22:30:00', 'UTC'));

        // 22:30 UTC ngày 10 = 05:30 sáng ngày 11 ở Hà Nội.
        $this->assertSame('2026-09-10', now()->format('Y-m-d'));
        $this->assertSame('2026-09-11', Gio::choONgay(now()));

        $this->travelBack();
    }

    /* ================= MỘT NƠI KHAI MÚI GIỜ ================= */

    #[Test]
    public function bao_cao_va_trang_hien_thi_dung_chung_mot_mui_gio(): void
    {
        /*
         * Khai hai nơi thì báo cáo gom theo một múi còn trang đơn hiện
         * theo múi khác — và con số trên biểu đồ không khớp với con số
         * người ta đếm bằng tay trên danh sách đơn.
         */
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
        /*
         * Ô trống nghĩa là "không đặt mốc" — khuyến mại cố ý để mở. Tự ý
         * điền một mốc vào là đặt hạn cho thứ người dùng muốn chạy mãi.
         */
        $this->assertSame([], Gio::doiONhap(['starts_at' => '', 'ends_at' => null], 'starts_at', 'ends_at'));
        $this->assertSame([], Gio::doiONhap([], 'starts_at'));

        $this->assertNull(Gio::hien(null));
        $this->assertNull(Gio::choO(null));
    }

    #[Test]
    public function o_gio_gui_len_kieu_la_thi_bo_qua_chu_khong_vo(): void
    {
        /*
         * BÀI NÀY SINH RA TỪ MỘT PHÉP ĐỘT BIẾN SỐNG SÓT.
         *
         * Bỏ hẳn kiểm tra kiểu trong doiONhap() mà mọi bài vẫn xanh — vì
         * chuỗi rỗng đã bị nhan() chặn ở lớp sau. Nhưng kiểm tra đó còn
         * chặn một thứ khác mà chưa bài nào đo: dữ liệu gửi lên KHÔNG
         * PHẢI CHUỖI.
         *
         * `starts_at[]=1` trên URL là một mảng. `trim([])` là TypeError —
         * cả trang vỡ thành lỗi 500, ngay trước bước kiểm tra dữ liệu,
         * nên không luật nào kịp bắt. Người gửi chỉ cần sửa địa chỉ trên
         * thanh URL.
         */
        $this->assertSame([], Gio::doiONhap(['starts_at' => ['2026-09-10T08:00']], 'starts_at'));
        $this->assertSame([], Gio::doiONhap(['starts_at' => 123], 'starts_at'));
        $this->assertSame([], Gio::doiONhap(['starts_at' => true], 'starts_at'));

        // Và đi qua đúng đường thật: biểu mẫu phải từ chối tử tế.
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
