<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\RefundMethod;
use App\Enums\RefundStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Refund;
use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use App\Services\Order\OrderException;
use App\Services\Order\OrderService;
use App\Services\Refund\RefundException;
use App\Services\Refund\RefundService;
use App\Services\Shop\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Hoàn tiền và hàng trả về.
 * ============================================================
 * ĐÂY LÀ TIỀN RA KHỎI CỬA HÀNG, nên cái sai đắt nhất là hoàn QUÁ hoặc
 * hoàn HAI LẦN. Phần lớn bài ở đây canh đúng hai chuyện đó, dưới mọi
 * đường vòng: hoàn nhiều lần, MoMo từ chối, MoMo im lặng, hai người xử lý
 * cùng một khoản đang chờ.
 *
 * Dùng Http::fake() cho MoMo, không gọi cổng thật.
 */
class RefundTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'khoa-bi-mat-thu';
    private const ACCESS_KEY = 'access-thu';
    private const PARTNER = 'PARTNERTHU';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.gateways.momo.enabled' => true,
            'payment.gateways.momo.partner_code' => self::PARTNER,
            'payment.gateways.momo.access_key' => self::ACCESS_KEY,
            'payment.gateways.momo.secret_key' => self::SECRET,
            'payment.gateways.momo.refund_endpoint' => 'https://momo.test/refund',
            'payment.gateways.momo.verify_ssl' => false,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    private function sp(int $ton = 10): Product
    {
        return Product::factory()->for(Category::factory())->create([
            'status' => 'active',
            'track_inventory' => true,
            'stock_quantity' => $ton,
        ]);
    }

    /**
     * Một đơn đã trả 300.000₫ gồm một dòng 3 × 100.000₫.
     *
     * forceFill vì `status` và `payment_status` cố ý nằm ngoài $fillable.
     */
    private function don(OrderStatus $trangThai, PaymentStatus $thanhToan = PaymentStatus::Paid, ?Product $sp = null, string $cach = 'cod'): Order
    {
        $order = Order::create([
            'order_number' => 'FP-HT-' . strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => $cach,
            'subtotal' => '300000.00',
            'discount_total' => '0.00',
            'shipping_fee' => '0.00',
            'coupon_discount' => '0.00',
            'grand_total' => '300000.00',
        ]);

        $order->forceFill(['status' => $trangThai, 'payment_status' => $thanhToan])->save();

        $sp ??= $this->sp();

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $sp->id,
            'product_name' => $sp->name,
            'unit_base_price' => '100000.00',
            'unit_price' => '100000.00',
            'quantity' => 3,
            'line_total' => '300000.00',
        ]);

        return $order->fresh();
    }

    private function donMomo(OrderStatus $trangThai, ?Product $sp = null): Order
    {
        $order = $this->don($trangThai, PaymentStatus::Paid, $sp, 'momo');

        PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway' => 'momo',
            'gateway_order_id' => $order->order_number . '-1',
            'transaction_id' => '4817048091',
            'amount' => '300000.00',
            'status' => PaymentTransactionStatus::Paid,
        ]);

        return $order->fresh();
    }

    private function svc(): RefundService
    {
        return app(RefundService::class);
    }

    private function chuyenKhoan(int $soTien, string $lyDo = 'don_huy', array $them = []): array
    {
        return array_merge([
            'amount' => $soTien,
            'reason' => $lyDo,
            'method' => 'chuyen_khoan',
            'reference' => 'FT' . random_int(100000, 999999),
        ], $them);
    }

    /* ================= 1. HOÀN ĐÚNG SỐ ================= */

    #[Test]
    public function hoan_du_cho_don_huy_thi_don_thanh_da_hoan_tien(): void
    {
        $this->actingAs($this->admin());
        $order = $this->don(OrderStatus::Cancelled);

        $r = $this->svc()->hoan($order, $this->chuyenKhoan(300000));

        $this->assertSame(RefundStatus::Completed, $r->status);
        $this->assertNotNull($r->completed_at);
        $this->assertNotNull($r->created_by, 'Phải ghi ai hoàn.');
        $this->assertSame(PaymentStatus::Refunded, $order->fresh()->payment_status);
    }

    #[Test]
    public function hoan_mot_phan_thi_van_la_da_thanh_toan_va_van_nhac_phan_con_lai(): void
    {
        /*
         * "Đã hoàn tiền" cho đơn mới hoàn 100.000₫ trong 300.000₫ là xoá
         * mất khoản cửa hàng còn nợ khách.
         */
        $order = $this->don(OrderStatus::Cancelled);

        $this->svc()->hoan($order, $this->chuyenKhoan(100000));

        $order = $order->fresh('refunds');

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame('200000.00', $order->refundableAmount());
        $this->assertTrue(app(OrderService::class)->owesRefund($order));
    }

    #[Test]
    public function KHONG_hoan_qua_so_khach_da_tra_qua_nhieu_lan(): void
    {
        $order = $this->don(OrderStatus::Cancelled);

        $this->svc()->hoan($order, $this->chuyenKhoan(200000));

        try {
            $this->svc()->hoan($order->fresh(), $this->chuyenKhoan(150000));
            $this->fail('Lần hoàn thứ hai vượt số đã trả phải bị chặn.');
        } catch (RefundException $e) {
            $this->assertStringContainsString(Money::format('100000'), $e->getMessage());
        }

        $this->assertSame(1, Refund::count());
    }

    #[Test]
    public function KHONG_hoan_don_dang_xu_ly(): void
    {
        $order = $this->don(OrderStatus::Shipping);

        $this->expectException(RefundException::class);
        $this->svc()->hoan($order, $this->chuyenKhoan(300000, 'khac'));
    }

    #[Test]
    public function KHONG_hoan_don_khach_chua_tra(): void
    {
        $order = $this->don(OrderStatus::Cancelled, PaymentStatus::Unpaid);

        $this->expectException(RefundException::class);
        $this->svc()->hoan($order, $this->chuyenKhoan(300000));
    }

    #[Test]
    public function chuyen_khoan_bat_buoc_co_ma_giao_dich(): void
    {
        $order = $this->don(OrderStatus::Cancelled);

        $this->expectException(RefundException::class);
        $this->svc()->hoan($order, $this->chuyenKhoan(300000, 'don_huy', ['reference' => '  ']));
    }

    #[Test]
    public function ly_do_phai_hop_trang_thai_don(): void
    {
        // "Hàng hỏng" cho một đơn chưa từng giao là một câu vô nghĩa.
        $order = $this->don(OrderStatus::Cancelled);

        $this->expectException(RefundException::class);
        $this->svc()->hoan($order, $this->chuyenKhoan(300000, 'hang_hong'));
    }

    #[Test]
    public function KHONG_bam_tay_da_hoan_tien_va_KHONG_go_da_tra_khi_da_co_hoan(): void
    {
        $order = $this->don(OrderStatus::Cancelled);

        try {
            app(OrderService::class)->setPaymentStatus($order, PaymentStatus::Refunded);
            $this->fail('Đặt tay "Đã hoàn tiền" phải bị chặn.');
        } catch (OrderException) {
        }

        $this->svc()->hoan($order, $this->chuyenKhoan(100000));

        // Gỡ "đã thanh toán" khi đã trả lại khách 100.000₫ là một khoản chi
        // không có khoản thu tương ứng.
        $this->expectException(OrderException::class);
        app(OrderService::class)->setPaymentStatus($order->fresh(), PaymentStatus::Unpaid);
    }

    /* ================= 2. HÀNG TRẢ VỀ ================= */

    #[Test]
    public function hang_tra_ve_con_ban_duoc_thi_cong_kho_hang_hong_thi_khong(): void
    {
        $sp = $this->sp(10);
        $order = $this->don(OrderStatus::Completed, sp: $sp);
        $dong = $order->items->first();

        $this->svc()->hoan($order, $this->chuyenKhoan(100000, 'tra_hang', [
            'items' => [$dong->id => ['quantity' => 1, 'restock' => '1']],
        ]));

        $this->assertSame(11, $sp->fresh()->stock_quantity);

        // Chậu vỡ khách gửi về: vẫn là hàng trả về, nhưng không bán lại được.
        $this->svc()->hoan($order->fresh(), $this->chuyenKhoan(100000, 'hang_hong', [
            'items' => [$dong->id => ['quantity' => 1, 'restock' => '0']],
        ]));

        $this->assertSame(11, $sp->fresh()->stock_quantity);
    }

    #[Test]
    public function KHONG_tra_ve_qua_so_da_mua(): void
    {
        $order = $this->don(OrderStatus::Completed);
        $dong = $order->items->first();

        $this->svc()->hoan($order, $this->chuyenKhoan(100000, 'tra_hang', [
            'items' => [$dong->id => ['quantity' => 2, 'restock' => '1']],
        ]));

        $this->expectException(RefundException::class);
        $this->svc()->hoan($order->fresh(), $this->chuyenKhoan(100000, 'tra_hang', [
            'items' => [$dong->id => ['quantity' => 2, 'restock' => '1']],
        ]));
    }

    #[Test]
    public function dong_hang_cua_don_KHAC_bi_tu_choi(): void
    {
        /*
         * id dòng đơn đến từ biểu mẫu. Không tra trong chính đơn đang hoàn
         * thì một id bịa trả được hàng của đơn người khác về kho.
         */
        $sp = $this->sp(10);
        $cuaNguoiKhac = $this->don(OrderStatus::Completed, sp: $sp)->items->first();
        $order = $this->don(OrderStatus::Completed);

        try {
            $this->svc()->hoan($order, $this->chuyenKhoan(100000, 'tra_hang', [
                'items' => [$cuaNguoiKhac->id => ['quantity' => 3, 'restock' => '1']],
            ]));
            $this->fail('Dòng hàng của đơn khác phải bị từ chối.');
        } catch (RefundException) {
        }

        $this->assertSame(10, $sp->fresh()->stock_quantity);
    }

    #[Test]
    public function don_huy_khong_nhan_hang_tra_ve(): void
    {
        // Hàng của đơn huỷ chưa rời cửa hàng, và đã được cộng lại kho lúc
        // huỷ. Cho nhận "trả về" nữa là cộng kho hai lần.
        $order = $this->don(OrderStatus::Cancelled);

        $this->expectException(RefundException::class);
        $this->svc()->hoan($order, $this->chuyenKhoan(100000, 'khac', [
            'items' => [$order->items->first()->id => ['quantity' => 1, 'restock' => '1']],
        ]));
    }

    #[Test]
    public function ly_do_khach_tra_hang_phai_ghi_hang_nao(): void
    {
        $order = $this->don(OrderStatus::Completed);

        $this->expectException(RefundException::class);
        $this->svc()->hoan($order, $this->chuyenKhoan(100000, 'tra_hang'));
    }

    /* ================= 3. HOÀN QUA MOMO ================= */

    #[Test]
    public function hoan_qua_momo_ky_dung_va_dung_giao_dich_goc(): void
    {
        Http::fake(['momo.test/refund' => Http::response(['resultCode' => 0, 'message' => 'Thành công', 'transId' => 999001])]);

        $this->actingAs($this->admin());
        $order = $this->donMomo(OrderStatus::Cancelled);

        $r = $this->svc()->hoan($order, ['amount' => 300000, 'reason' => 'don_huy', 'method' => 'momo']);

        $this->assertSame(RefundStatus::Completed, $r->status);
        $this->assertSame('999001', $r->reference);
        $this->assertSame(PaymentStatus::Refunded, $order->fresh()->payment_status);

        Http::assertSent(function ($req) use ($r) {
            $d = $req->data();
            $raw = 'accessKey=' . self::ACCESS_KEY . '&amount=300000&description=' . $d['description']
                . '&orderId=' . $r->code . '&partnerCode=' . self::PARTNER . '&requestId=' . $r->code
                . '&transId=4817048091';

            return (int) $d['transId'] === 4817048091
                && hash_equals(hash_hmac('sha256', $raw, self::SECRET), $d['signature']);
        });
    }

    #[Test]
    public function momo_tu_choi_thi_khong_thanh_cong_va_nha_so_tien(): void
    {
        Http::fake(['momo.test/refund' => Http::response(['resultCode' => 1080, 'message' => 'Giao dịch không hợp lệ'])]);

        $order = $this->donMomo(OrderStatus::Cancelled);

        $r = $this->svc()->hoan($order, ['amount' => 300000, 'reason' => 'don_huy', 'method' => 'momo']);

        $order = $order->fresh('refunds');

        $this->assertSame(RefundStatus::Failed, $r->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame('300000.00', $order->refundableAmount(), 'Lần thất bại không được giữ chỗ tiền.');
    }

    #[Test]
    public function momo_IM_LANG_thi_giu_cho_va_chan_hoan_lan_nua(): void
    {
        /*
         * BÀI QUAN TRỌNG NHẤT CỦA PHẦN MOMO.
         *
         * Mất kết nối không có nghĩa MoMo chưa hoàn. Coi là thất bại thì số
         * tiền được nhả ra, admin bấm hoàn lại, và khách nhận tiền hai lần.
         */
        Http::fake(fn () => throw new ConnectionException('hết thời gian chờ'));

        $order = $this->donMomo(OrderStatus::Cancelled);

        $r = $this->svc()->hoan($order, ['amount' => 300000, 'reason' => 'don_huy', 'method' => 'momo']);

        $this->assertSame(RefundStatus::Pending, $r->status);
        $this->assertFalse(app(OrderService::class)->owesRefund($order->fresh('refunds')),
            'Khoản đang chờ đã giữ đủ số tiền — không được nhắc hoàn thêm.');

        $this->expectException(RefundException::class);
        $this->svc()->hoan($order->fresh(), $this->chuyenKhoan(300000));
    }

    #[Test]
    public function hang_tra_ve_chi_vao_kho_khi_khoan_dang_cho_duoc_xac_nhan(): void
    {
        Http::fake(fn () => throw new ConnectionException('hết thời gian chờ'));

        $sp = $this->sp(10);
        $order = $this->donMomo(OrderStatus::Completed, $sp);
        $dong = $order->items->first();

        $r = $this->svc()->hoan($order, [
            'amount' => 100000, 'reason' => 'tra_hang', 'method' => 'momo',
            'items' => [$dong->id => ['quantity' => 1, 'restock' => '1']],
        ]);

        $this->assertSame(10, $sp->fresh()->stock_quantity, 'Chưa rõ tiền đã đi thì hàng chưa vào kho.');

        $this->svc()->xacNhanDaHoan($r, '999777');

        $this->assertSame(RefundStatus::Completed, $r->fresh()->status);
        $this->assertSame(11, $sp->fresh()->stock_quantity);
    }

    #[Test]
    public function danh_dau_that_bai_thi_nha_tien_va_khong_xu_ly_lan_hai(): void
    {
        Http::fake(fn () => throw new ConnectionException('hết thời gian chờ'));

        $order = $this->donMomo(OrderStatus::Cancelled);
        $r = $this->svc()->hoan($order, ['amount' => 300000, 'reason' => 'don_huy', 'method' => 'momo']);

        $this->svc()->danhDauThatBai($r);

        $this->assertSame('300000.00', $order->fresh('refunds')->refundableAmount());

        // Người thứ hai xác nhận "đã hoàn" sau khi người thứ nhất đã ghi
        // thất bại: phải bị chặn, không được ghi đè.
        $this->expectException(RefundException::class);
        $this->svc()->xacNhanDaHoan($r, '123');
    }

    #[Test]
    public function don_COD_khong_co_lua_chon_hoan_qua_momo(): void
    {
        $order = $this->don(OrderStatus::Cancelled);

        $this->assertNotContains(RefundMethod::Momo, $this->svc()->cachHoan($order));

        $this->expectException(RefundException::class);
        $this->svc()->hoan($order, ['amount' => 300000, 'reason' => 'don_huy', 'method' => 'momo']);
    }

    /* ================= 4. MÀN HÌNH ================= */

    #[Test]
    public function admin_ghi_hoan_tien_qua_bieu_mau_va_co_nhat_ky(): void
    {
        $order = $this->don(OrderStatus::Cancelled);

        $this->actingAs($this->admin())
            ->post(route('admin.orders.refunds.store', $order), $this->chuyenKhoan(300000))
            ->assertSessionHas('success');

        $this->assertSame(PaymentStatus::Refunded, $order->fresh()->payment_status);
        $this->assertTrue(ActivityLog::where('action', 'don-hang.hoan-tien')->exists());
    }

    #[Test]
    public function khach_thuong_khong_goi_duoc_route_hoan_tien(): void
    {
        $order = $this->don(OrderStatus::Cancelled);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.orders.refunds.store', $order), $this->chuyenKhoan(300000))
            ->assertForbidden();

        $this->assertSame(0, Refund::count());
    }

    #[Test]
    public function trang_don_admin_hien_lich_su_va_so_con_hoan_duoc(): void
    {
        $order = $this->don(OrderStatus::Cancelled);
        $r = $this->svc()->hoan($order, $this->chuyenKhoan(100000));

        $this->actingAs($this->admin())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee($r->code)
            ->assertSee('Ghi hoàn tiền')
            ->assertDontSee('>Đã hoàn tiền</button>', false);
    }

    #[Test]
    public function KHONG_bay_nut_go_danh_dau_ma_bam_vao_chac_chan_loi(): void
    {
        /*
         * OrderService từ chối gỡ "đã thanh toán" cho đơn đã giao và cho đơn
         * đã có khoản hoàn. Bày nút ra ở đó là một nút lúc nào bấm cũng báo
         * lỗi — thứ giao diện không được có.
         */
        $admin = $this->admin();

        $daGiao = $this->don(OrderStatus::Completed);
        $this->actingAs($admin)->get(route('admin.orders.show', $daGiao))->assertDontSee('Gỡ đánh dấu');

        $daHoanMotPhan = $this->don(OrderStatus::Cancelled);
        $this->svc()->hoan($daHoanMotPhan, $this->chuyenKhoan(100000));
        $this->actingAs($admin)->get(route('admin.orders.show', $daHoanMotPhan))->assertDontSee('Gỡ đánh dấu');

        // Mặt còn lại: đơn đang xử lý, chưa hoàn gì, vẫn gỡ được khi bấm nhầm.
        $dangXuLy = $this->don(OrderStatus::Confirmed);
        $this->actingAs($admin)->get(route('admin.orders.show', $dangXuLy))->assertSee('Gỡ đánh dấu');
    }

    #[Test]
    public function khach_thay_tien_da_hoan_nhung_KHONG_thay_lan_that_bai(): void
    {
        Http::fake(['momo.test/refund' => Http::response(['resultCode' => 1080, 'message' => 'lỗi'])]);

        $khach = User::factory()->create();
        $order = $this->donMomo(OrderStatus::Cancelled);
        $order->forceFill(['user_id' => $khach->id])->save();

        $thatBai = $this->svc()->hoan($order, ['amount' => 300000, 'reason' => 'don_huy', 'method' => 'momo']);
        $xong = $this->svc()->hoan($order->fresh(), $this->chuyenKhoan(300000));

        $this->actingAs($khach)
            ->get(route('shop.orders.show', $order))
            ->assertOk()
            ->assertSee('Tiền đã hoàn lại')
            ->assertSee($xong->code)
            ->assertDontSee($thatBai->code);
    }

    /* ================= 5. DOANH THU VÀ HÀNG ĐỢI ================= */

    #[Test]
    public function doanh_thu_thuan_tru_hoan_tien_cua_don_da_giao_KHONG_tru_don_huy(): void
    {
        $daGiao = $this->don(OrderStatus::Completed);
        $this->svc()->hoan($daGiao, $this->chuyenKhoan(100000, 'hang_hong'));

        // Tiền hoàn của đơn huỷ chưa bao giờ nằm trong doanh thu.
        $huy = $this->don(OrderStatus::Cancelled);
        $this->svc()->hoan($huy, $this->chuyenKhoan(300000));

        $s = app(AnalyticsService::class)->forPeriod('30')->orderStats();

        $this->assertSame(300000.0, $s['revenue']);
        $this->assertSame(100000.0, $s['refunded']);
        $this->assertSame(200000.0, $s['net_revenue']);
    }

    #[Test]
    public function tong_quan_va_phan_tich_cung_dua_doanh_thu_thuan_len_dau(): void
    {
        $order = $this->don(OrderStatus::Completed);
        $this->svc()->hoan($order, $this->chuyenKhoan(100000, 'hang_hong'));

        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/dashboard?ky=30')->assertSee('Doanh thu thuần')->assertSee(Money::format('200000'));
        $this->actingAs($admin)->get('/admin/phan-tich?ky=30')->assertSee('Doanh thu thuần')->assertSee(Money::format('200000'));
    }

    #[Test]
    public function hang_doi_viec_nhac_khoan_momo_chua_ro_va_dan_toi_dung_don(): void
    {
        Http::fake(fn () => throw new ConnectionException('hết thời gian chờ'));

        $order = $this->donMomo(OrderStatus::Cancelled);
        $khac = $this->don(OrderStatus::Cancelled);
        $this->svc()->hoan($order, ['amount' => 300000, 'reason' => 'don_huy', 'method' => 'momo']);

        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/dashboard')->assertSee('đơn có khoản hoàn tiền MoMo chưa rõ kết quả');

        $this->actingAs($admin)
            ->get(route('admin.orders.index', ['hoan_tien' => 'chua-ro']))
            ->assertSee($order->order_number)
            ->assertDontSee($khac->order_number);
    }

    #[Test]
    public function tep_xuat_liet_ke_cac_lan_hoan(): void
    {
        $order = $this->don(OrderStatus::Cancelled);
        $r = $this->svc()->hoan($order, $this->chuyenKhoan(300000));

        $csv = $this->actingAs($this->admin())
            ->get('/admin/phan-tich/xuat/tai-ve?' . http_build_query(['ky' => '30', 'dinh_dang' => 'csv', 'phan' => ['hoan-tien']]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString($r->code, $csv);
        $this->assertStringContainsString($order->order_number, $csv);
    }
}
