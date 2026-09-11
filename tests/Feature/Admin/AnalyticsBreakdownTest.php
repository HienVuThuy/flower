<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockReceiptStatus;
use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Review;
use App\Models\StockReceipt;
use App\Models\User;
use App\Services\Analytics\AbandonedCarts;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\ProfitReport;
use App\Services\Analytics\ReviewReport;
use App\Services\Analytics\SalesBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Các trang con của Phân tích: doanh thu, khách hàng, đánh giá, lợi nhuận.
 * ============================================================
 * Mỗi nhóm bài canh ĐÚNG chỗ dễ sai nhất của báo cáo đó — những chỗ mà con
 * số sai vẫn hiện ra bình thường, không có lỗi nào báo:
 *
 *   - giờ lưu là UTC, người đọc ở giờ Việt Nam;
 *   - cùng một tỉnh viết hai kiểu;
 *   - "khách mới" tính trong kỳ thay vì trên toàn lịch sử;
 *   - giỏ rỗng hoặc giỏ đang mua bị gọi là bỏ dở;
 *   - giá vốn của lô nhập SAU quyết định lãi của hàng bán TRƯỚC.
 */
class AnalyticsBreakdownTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    private function sp(string $danhMuc = 'Hoa', string $gia = '300000.00'): Product
    {
        $cat = Category::where('name', $danhMuc)->first() ?? Category::factory()->create(['name' => $danhMuc]);

        return Product::factory()->for($cat)->price($gia)->create(['status' => 'active']);
    }

    /**
     * Một đơn với một dòng hàng. `$luc` là mốc THEO GIỜ LƯU (UTC).
     *
     * forceFill cho status, created_at, user_id: cố ý nằm ngoài $fillable.
     */
    private function don(array $o = []): Order
    {
        $o += [
            'trang_thai' => OrderStatus::Completed,
            'tien' => '300000.00',
            'luc' => now()->subDay(),
            'tinh' => 'Thành phố Hà Nội',
            'user' => null,
            'sp' => null,
            'sl' => 1,
            'giam_dong' => '0.00',
            'thue_dong' => null,
            'thue_don' => null,
            'ma_giam' => '0.00',
        ];

        $order = Order::create([
            'order_number' => 'FP-PT-' . strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => $o['tinh'],
            'payment_method' => 'cod',
            'subtotal' => $o['tien'],
            'discount_total' => '0.00',
            'shipping_fee' => '0.00',
            'coupon_discount' => $o['ma_giam'],
            'grand_total' => $o['tien'],
            'tax_amount' => $o['thue_don'],
        ]);

        $order->forceFill([
            'status' => $o['trang_thai'],
            'payment_status' => PaymentStatus::Paid,
            'created_at' => $o['luc'],
            'user_id' => $o['user']?->id,
        ])->save();

        $sp = $o['sp'] ?? $this->sp();

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $sp->id,
            'product_name' => $sp->name,
            'unit_base_price' => $o['tien'],
            'unit_price' => bcdiv($o['tien'], (string) $o['sl'], 2),
            'quantity' => $o['sl'],
            'line_total' => $o['tien'],
            'discount_amount' => $o['giam_dong'],
            'tax_amount' => $o['thue_dong'],
        ]);

        return $order;
    }

    private function kyHienTai(string $ky)
    {
        return app(AnalyticsService::class)->forPeriod($ky)->khoang();
    }

    /* ================= 1. GIỜ VIỆT NAM ================= */

    #[Test]
    public function don_6h30_sang_gio_viet_nam_thuoc_dung_ngay_do(): void
    {
        /*
         * 23:30 UTC ngày 10 = 06:30 sáng ngày 11 ở Hà Nội. Gom theo DATE() của
         * SQL thì đơn này nằm ở ngày 10 — biểu đồ doanh thu lệch một ngày cho
         * mọi đơn đặt từ 0h tới 7h sáng.
         */
        Carbon::setTestNow(Carbon::parse('2026-09-12 05:00:00', 'UTC'));

        $this->don(['luc' => Carbon::parse('2026-09-10 23:30:00', 'UTC'), 'tien' => '450000.00']);

        $ngay = app(AnalyticsService::class)->forPeriod('7')->revenueByDay()->keyBy('date');

        $this->assertSame(450000.0, $ngay['2026-09-11']['revenue']);
        $this->assertSame(0.0, $ngay['2026-09-10']['revenue']);
    }

    #[Test]
    public function khung_gio_dem_theo_gio_viet_nam(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 05:00:00', 'UTC'));

        $luc = Carbon::parse('2026-09-10 23:30:00', 'UTC');
        $this->don(['luc' => $luc]);

        $g = app(SalesBreakdown::class)->trong($this->kyHienTai('7'))->theoKhungGio();

        $dp = $luc->copy()->setTimezone('Asia/Ho_Chi_Minh');

        $this->assertSame(1, $g['o'][$dp->isoWeekday() - 1][6], 'Phải là ô 6h sáng giờ Việt Nam, không phải 23h.');
        $this->assertSame(0, $g['theo_gio'][23]);
    }

    #[Test]
    public function ky_7_ngay_bat_dau_tu_nua_dem_gio_viet_nam(): void
    {
        /*
         * Nửa đêm UTC là 7h sáng ở Hà Nội. Cắt mốc theo UTC thì đơn đặt lúc
         * 1h sáng ngày đầu kỳ bị loại khỏi "7 ngày qua".
         */
        Carbon::setTestNow(Carbon::parse('2026-09-12 05:00:00', 'UTC')); // 12h trưa 12/09 giờ VN

        // 00:30 ngày 05/09 giờ VN = 17:30 UTC ngày 04/09 — thuộc kỳ.
        $this->don(['luc' => Carbon::parse('2026-09-04 17:30:00', 'UTC'), 'tien' => '100000.00']);
        // 23:30 ngày 04/09 giờ VN = 16:30 UTC ngày 04/09 — ngoài kỳ.
        $this->don(['luc' => Carbon::parse('2026-09-04 16:30:00', 'UTC'), 'tien' => '999000.00']);

        $s = app(AnalyticsService::class)->forPeriod('7')->orderStats();

        $this->assertSame(100000.0, $s['revenue']);
    }

    /* ================= 2. DANH MỤC ================= */

    #[Test]
    public function doanh_thu_danh_muc_tru_phan_ma_giam_da_chia_ve_dong(): void
    {
        $this->don(['sp' => $this->sp('Cây cảnh'), 'tien' => '300000.00', 'giam_dong' => '30000.00', 'ma_giam' => '30000.00']);
        $this->don(['sp' => $this->sp('Hoa'), 'tien' => '100000.00']);

        $dm = app(SalesBreakdown::class)->trong($this->kyHienTai('30'))->theoDanhMuc();
        $theoTen = $dm['dong']->keyBy('ten');

        $this->assertSame('270000.00', $theoTen['Cây cảnh']['doanh_thu']);
        $this->assertSame('370000.00', $dm['tong']);
        $this->assertSame('0.00', $dm['ma_giam_chua_chia']);
    }

    #[Test]
    public function ma_giam_cua_don_cu_chua_chia_ve_dong_thi_noi_ra(): void
    {
        // Đơn cũ: mã giảm 50.000₫ ở đầu đơn, nhưng các dòng không mang phần nào.
        $this->don(['ma_giam' => '50000.00', 'giam_dong' => '0.00']);

        $dm = app(SalesBreakdown::class)->trong($this->kyHienTai('30'))->theoDanhMuc();

        $this->assertSame('50000.00', $dm['ma_giam_chua_chia']);
    }

    /* ================= 3. TỈNH ================= */

    #[Test]
    public function ha_noi_va_thanh_pho_ha_noi_la_mot_dong(): void
    {
        /*
         * Dữ liệu thật: "Hà Nội" 1 đơn, "Thành phố Hà Nội" 19 đơn. Gom theo chuỗi
         * là hai dòng, và dòng lớn thấp hơn sự thật.
         */
        $this->don(['tinh' => 'Thành phố Hà Nội', 'tien' => '200000.00']);
        $this->don(['tinh' => 'Thành phố Hà Nội', 'tien' => '200000.00']);
        $this->don(['tinh' => 'Hà Nội', 'tien' => '100000.00']);
        $this->don(['tinh' => 'Tỉnh Bắc Ninh', 'tien' => '50000.00']);

        $tinh = app(SalesBreakdown::class)->trong($this->kyHienTai('30'))->theoTinh();

        $this->assertCount(2, $tinh);
        $this->assertSame('Thành phố Hà Nội', $tinh[0]['ten'], 'Nhãn là cách viết gặp nhiều nhất.');
        $this->assertSame(3, $tinh[0]['so_don']);
        $this->assertSame('500000.00', $tinh[0]['thuan']);
    }

    #[Test]
    public function doanh_thu_tinh_tru_hoan_tien(): void
    {
        $order = $this->don(['tien' => '300000.00']);

        $r = Refund::create(['order_id' => $order->id, 'code' => 'HT-THU-0001', 'amount' => '100000.00', 'reason' => 'hang_hong', 'method' => 'tien_mat']);
        $r->forceFill(['status' => 'completed', 'completed_at' => now()])->save();

        $tinh = app(SalesBreakdown::class)->trong($this->kyHienTai('30'))->theoTinh();

        $this->assertSame('100000.00', $tinh[0]['hoan_tien']);
        $this->assertSame('200000.00', $tinh[0]['thuan']);
    }

    /* ================= 4. KHÁCH MỚI / QUAY LẠI ================= */

    #[Test]
    public function khach_mua_tu_truoc_ky_van_la_khach_quay_lai(): void
    {
        /*
         * "Đơn đầu tiên" tính trên TOÀN BỘ lịch sử. Tính trong kỳ thì khách mua
         * từ 2 tháng trước, tháng này quay lại, bị gọi là khách mới.
         */
        $cu = User::factory()->create();
        $moi = User::factory()->create();

        $this->don(['user' => $cu, 'luc' => now()->subDays(60)]);
        $this->don(['user' => $cu, 'luc' => now()->subDays(2), 'tien' => '400000.00']);
        $this->don(['user' => $moi, 'luc' => now()->subDays(3), 'tien' => '150000.00']);
        $this->don(['user' => null, 'luc' => now()->subDays(1), 'tien' => '90000.00']);

        $k = app(SalesBreakdown::class)->trong($this->kyHienTai('30'))->khachMoiVaQuayLai();

        $this->assertSame(['khach' => 1, 'don' => 1, 'doanh_thu' => '150000.00'], $k['moi']);
        $this->assertSame(['khach' => 1, 'don' => 1, 'doanh_thu' => '400000.00'], $k['quay_lai']);
        $this->assertSame(['don' => 1, 'doanh_thu' => '90000.00'], $k['vang_lai']);
        $this->assertSame(50.0, $k['ti_le_mua_lai']);
    }

    /* ================= 5. GIỎ BỎ DỞ ================= */

    private function gio(?User $user, array $dong, Carbon $lanCuoi): Cart
    {
        $cart = Cart::create(['user_id' => $user?->id, 'session_id' => $user ? null : 'phien-' . bin2hex(random_bytes(4))]);

        foreach ($dong as [$sp, $sl]) {
            $cart->items()->create(['product_id' => $sp->id, 'quantity' => $sl]);
        }

        $cart->forceFill(['updated_at' => $lanCuoi])->saveQuietly();
        $cart->items()->update(['updated_at' => $lanCuoi]);

        return $cart;
    }

    #[Test]
    public function chi_gio_con_hang_va_bo_qua_24_gio_moi_la_bo_do(): void
    {
        $sp = $this->sp('Hoa', '120000.00');

        Cart::create(['user_id' => User::factory()->create()->id]); // giỏ rỗng
        $this->gio(User::factory()->create(), [[$sp, 1]], now()->subHours(2)); // đang mua
        $this->gio(User::factory()->create(), [[$sp, 2]], now()->subDays(3)); // bỏ dở

        $b = app(AbandonedCarts::class)->baoCao();

        $this->assertSame(1, $b['so_gio']);
        $this->assertSame('240000.00', $b['tong_gia_tri']);
    }

    #[Test]
    public function khach_da_dat_don_sau_lan_sua_gio_thi_khong_tinh(): void
    {
        $khach = User::factory()->create();
        $sp = $this->sp();

        $this->gio($khach, [[$sp, 1]], now()->subDays(5));
        $this->don(['user' => $khach, 'luc' => now()->subDays(4), 'trang_thai' => OrderStatus::Pending]);

        $this->assertSame(0, app(AbandonedCarts::class)->baoCao()['so_gio']);
    }

    #[Test]
    public function gia_tri_gio_theo_gia_hien_tai_va_khong_cong_gia_lien_he_nhu_0d(): void
    {
        $coGia = $this->sp('Hoa', '200000.00');
        $lienHe = $this->sp('Hoa sự kiện');
        $lienHe->forceFill(['base_price' => null])->save();

        $this->gio(null, [[$coGia, 1], [$lienHe, 1]], now()->subDays(2));

        // Đổi giá SAU khi bỏ vào giỏ: giá trị phải theo giá hôm nay.
        $coGia->forceFill(['base_price' => '250000.00'])->save();

        $g = app(AbandonedCarts::class)->baoCao()['gio'][0];

        $this->assertSame('250000.00', $g['gia_tri']);
        $this->assertSame(1, $g['khong_dinh_gia']);
        $this->assertTrue($g['vang_lai']);
    }

    /* ================= 6. ĐÁNH GIÁ ================= */

    private function danhGia(Product $sp, int $sao, ?Carbon $traLoi = null): Review
    {
        $r = Review::create([
            'product_id' => $sp->id,
            'user_id' => User::factory()->create()->id,
            'rating' => $sao,
            'comment' => 'Nhận xét',
        ]);

        $r->forceFill(['admin_reply' => $traLoi ? 'Cảm ơn' : null, 'admin_replied_at' => $traLoi])->save();

        return $r;
    }

    #[Test]
    public function khong_co_danh_gia_thi_trung_binh_la_null_va_du_5_muc(): void
    {
        $t = app(ReviewReport::class)->trong($this->kyHienTai('30'))->tongQuan();

        $this->assertNull($t['trung_binh'], '"0 sao" là một lời chê, không phải "chưa có bài".');
        $this->assertSame([5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0], $t['phan_bo']);
    }

    #[Test]
    public function san_pham_mot_bai_khong_vao_bang_bi_che_va_trung_vi_khong_bi_keo(): void
    {
        $motBai = $this->sp();
        $nhieuBai = $this->sp();

        $this->danhGia($motBai, 1);
        $this->danhGia($nhieuBai, 3, now()->addHours(2));
        $this->danhGia($nhieuBai, 2, now()->addHours(4));
        // Một bài trả lời sau 90 ngày: kéo trung bình lên hàng nghìn giờ.
        $this->danhGia($nhieuBai, 4, now()->addDays(90));

        $bao = app(ReviewReport::class)->trong($this->kyHienTai('30'));

        $this->assertSame([$nhieuBai->name], $bao->sanPhamBiCheNhieu()->pluck('ten')->all());
        $this->assertEqualsWithDelta(4.0, $bao->tongQuan()['gio_tra_loi_trung_vi'], 0.1);
    }

    /* ================= 7. LÃI GỘP ================= */

    private function phieuNhap(Product $sp, int $sl, string $gia, string $ngay): void
    {
        $p = StockReceipt::create(['code' => 'NK-' . strtoupper(bin2hex(random_bytes(3))), 'received_at' => $ngay]);
        $p->forceFill(['status' => StockReceiptStatus::Posted, 'posted_at' => now()])->save();
        $p->items()->create([
            'product_id' => $sp->id,
            'product_name' => $sp->name,
            'quantity' => $sl,
            'unit_cost' => $gia,
        ]);
    }

    #[Test]
    public function khong_co_gia_von_thi_khong_tinh_lai_va_noi_phan_bi_loai(): void
    {
        $this->don(['tien' => '300000.00']);

        $l = app(ProfitReport::class)->trong($this->kyHienTai('30'))->baoCao();

        $this->assertFalse($l['co_phieu_nhap_co_gia']);
        $this->assertSame(0.0, $l['ti_le_phu']);
        $this->assertNull($l['bien']);
        $this->assertSame(1, $l['dong_khong_gia_von']);
        $this->assertTrue($l['theo_san_pham']->isEmpty());
    }

    #[Test]
    public function gia_von_la_binh_quan_cac_lan_nhap_TOI_NGAY_BAN(): void
    {
        /*
         * Lô nhập ngày 20 không được quyết định giá vốn của hàng bán ngày 15.
         * Bán ngày 15: giá vốn = lô ngày 1 (100k). Bán ngày 25: bình quân
         * (10×100k + 10×200k)/20 = 150k.
         */
        Carbon::setTestNow(Carbon::parse('2026-09-28 05:00:00', 'UTC'));

        $sp = $this->sp();
        $this->phieuNhap($sp, 10, '100000.00', '2026-09-01');
        $this->phieuNhap($sp, 10, '200000.00', '2026-09-20');

        $this->don(['sp' => $sp, 'tien' => '300000.00', 'luc' => Carbon::parse('2026-09-15 03:00:00', 'UTC')]);
        $this->don(['sp' => $sp, 'tien' => '300000.00', 'luc' => Carbon::parse('2026-09-25 03:00:00', 'UTC')]);

        $l = app(ProfitReport::class)->trong($this->kyHienTai('30'))->baoCao();

        $this->assertSame('250000.00', $l['gia_von']);
        $this->assertSame('350000.00', $l['lai_gop']);
        $this->assertSame(100.0, $l['ti_le_phu']);
    }

    #[Test]
    public function hang_ban_truoc_lan_nhap_co_gia_dau_tien_khong_co_gia_von(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 05:00:00', 'UTC'));

        $sp = $this->sp();
        $this->phieuNhap($sp, 10, '100000.00', '2026-09-20');

        $this->don(['sp' => $sp, 'tien' => '300000.00', 'luc' => Carbon::parse('2026-09-10 03:00:00', 'UTC')]);

        $l = app(ProfitReport::class)->trong($this->kyHienTai('30'))->baoCao();

        $this->assertSame(1, $l['dong_khong_gia_von']);
        $this->assertSame('0.00', $l['gia_von']);
    }

    #[Test]
    public function doanh_thu_tinh_lai_da_tru_ma_giam_va_vat_va_noi_don_chua_co_so_thue(): void
    {
        $sp = $this->sp();
        $this->phieuNhap($sp, 10, '100000.00', now()->subDays(20)->toDateString());

        // 300k − 30k mã giảm − 20k VAT = 250k doanh thu.
        $this->don(['sp' => $sp, 'tien' => '300000.00', 'giam_dong' => '30000.00', 'thue_dong' => '20000.00', 'thue_don' => '20000.00']);
        // Đơn chưa có số liệu thuế: không có gì để trừ, và phải được đếm.
        $this->don(['sp' => $sp, 'tien' => '300000.00']);

        $l = app(ProfitReport::class)->trong($this->kyHienTai('30'))->baoCao();

        $this->assertSame('550000.00', $l['doanh_thu']);
        $this->assertSame(1, $l['dong_chua_tach_vat']);
    }

    /* ================= 8. CÁC TRANG ================= */

    #[Test]
    public function cac_trang_con_mo_duoc_va_tab_giu_ky_dang_chon(): void
    {
        $this->don();
        $admin = $this->admin();

        foreach (['/admin/phan-tich/doanh-thu', '/admin/phan-tich/khach-hang', '/admin/phan-tich/danh-gia', '/admin/phan-tich/loi-nhuan', '/admin/phan-tich'] as $url) {
            $html = $this->actingAs($admin)->get($url . '?ky=7')->assertOk()->getContent();

            $this->assertStringContainsString('phan-tich/khach-hang?ky=7', $html, "Tab ở {$url} phải giữ kỳ đang chọn.");
            $this->assertMatchesRegularExpression('#class="analytics-tabs__tab is-active"#', $html);
        }
    }

    #[Test]
    public function xuat_duoc_tung_bao_cao_moi_va_so_trong_tep_khop_trang(): void
    {
        /*
         * Tệp xuất gọi CHÍNH lớp tính mà trang admin gọi. Bài này mở trang và
         * tệp với cùng dữ liệu, đòi cùng một con số.
         */
        $this->don(['tinh' => 'Hà Nội', 'tien' => '120000.00']);
        $this->don(['tinh' => 'Thành phố Hà Nội', 'tien' => '180000.00']);

        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/phan-tich/doanh-thu?ky=30')
            ->assertSee(\App\Services\Shop\Money::format('300000'));

        $csv = $this->actingAs($admin)
            ->get('/admin/phan-tich/xuat/tai-ve?' . http_build_query(['ky' => '30', 'dinh_dang' => 'csv', 'phan' => ['dt-tinh', 'khung-gio', 'lai-gop']]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('300000.00', $csv);
        $this->assertStringContainsString('23h', $csv);
        $this->assertStringContainsString('KHÔNG tính vào lãi', $csv);
    }

    #[Test]
    public function khach_thuong_khong_vao_duoc_trang_phan_tich(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/phan-tich/loi-nhuan')
            ->assertForbidden();
    }
}
