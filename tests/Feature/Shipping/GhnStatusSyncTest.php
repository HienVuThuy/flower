<?php

namespace Tests\Feature\Shipping;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Services\Shipping\GhnStatusSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tự động ghi nhận tiền COD từ trạng thái vận đơn GHN.
 * ============================================================
 * ĐÂY LÀ BÀI KIỂM THỬ QUAN TRỌNG NHẤT CỦA PHẦN TỰ ĐỘNG HOÁ THANH TOÁN.
 *
 * Trước đây admin phải mở đơn ra, tự hỏi shipper, rồi bấm "Đã thanh
 * toán". Nay GHN — chính là shipper — trả lời bằng API, và phần mềm đọc
 * câu trả lời đó.
 *
 * Vì thứ được tự động hoá là TIỀN, cái sai đắt nhất không phải "quên ghi
 * nhận" mà là "ghi nhận nhầm": đánh dấu đã thu tiền cho một đơn shipper
 * còn đang trên đường thì cửa hàng không bao giờ đi đòi nữa. Vì vậy có
 * hẳn một bài riêng cho `delivering`.
 *
 * Dùng Http::fake() chứ không gọi GHN thật: cổng thử của GHN hay ngắt
 * kết nối, và một bài kiểm thử lúc xanh lúc đỏ là bài kiểm thử người ta
 * học cách phớt lờ.
 */
class GhnStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.ghn.base_url', 'https://ghn.test/api');
        config()->set('services.ghn.token', 'token-thu');
        config()->set('services.ghn.shop_id', 1234);
    }

    /** Đơn đã tạo vận đơn và đang trên đường. */
    private function donDangGiao(
        OrderStatus $status = OrderStatus::Shipping,
        PaymentMethod $method = PaymentMethod::Cod,
        string $shippingStatus = 'picked',
    ): Order {
        $order = Order::create([
            'order_number' => 'FP-TEST-' . strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => $method->value,
            'subtotal' => '300000.00',
            'discount_total' => '0.00',
            'shipping_fee' => '25000.00',
            'coupon_discount' => '0.00',
            'grand_total' => '325000.00',
        ]);

        $order->status = $status;
        $order->payment_status = PaymentStatus::Unpaid;
        $order->ghn_order_code = 'GHN123456';
        $order->shipping_status = $shippingStatus;
        $order->save();

        return $order;
    }

    private function ghnTraVe(string $status): void
    {
        Http::fake([
            '*/v2/shipping-order/detail' => Http::response([
                'code' => 200,
                'data' => ['status' => $status],
            ]),
        ]);
    }

    #[Test]
    public function ghn_bao_da_giao_thi_don_cod_tu_dong_thanh_da_thanh_toan(): void
    {
        $order = $this->donDangGiao();
        $this->ghnTraVe('delivered');

        app(GhnStatusSync::class)->syncOne($order);

        $order->refresh();

        $this->assertSame('delivered', $order->shipping_status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Completed, $order->status);
    }

    #[Test]
    public function dang_tren_duong_giao_thi_tuyet_doi_khong_duoc_ghi_la_da_thu_tien(): void
    {
        /*
         * BÀI QUAN TRỌNG NHẤT TỆP NÀY.
         *
         * `delivering` = shipper đang cầm hàng đi tới nhà khách. Khách
         * vẫn có quyền từ chối nhận ngay tại cửa. Đánh dấu đã thu tiền ở
         * đây là cửa hàng ghi vào sổ một khoản không tồn tại, và sẽ
         * không bao giờ đi đòi.
         */
        $order = $this->donDangGiao(status: OrderStatus::Preparing);
        $this->ghnTraVe('delivering');

        app(GhnStatusSync::class)->syncOne($order);

        $order->refresh();

        $this->assertSame('delivering', $order->shipping_status);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status, 'Chưa giao xong mà đã ghi là thu được tiền.');

        // Nhưng trạng thái đơn thì phải theo kịp: hàng đã rời cửa hàng.
        $this->assertSame(OrderStatus::Shipping, $order->status);
    }

    #[Test]
    public function ghn_bao_da_giao_khi_don_moi_chi_dang_xac_nhan_thi_di_qua_tung_buoc(): void
    {
        /*
         * GHN không biết gì về các bậc trạng thái của cửa hàng. Nếu tác
         * vụ đồng bộ không chạy hai ngày, nó có thể nhảy thẳng từ lúc
         * vừa tạo vận đơn sang `delivered`.
         *
         * OrderStatus chỉ cho đi từng bậc liền kề, nên phải đi hết
         * Confirmed → Preparing → Shipping → Completed. Ép thẳng sang
         * đích là bỏ qua luật chuyển trạng thái và bỏ qua luôn thư báo
         * cho khách ở mỗi bước.
         */
        $order = $this->donDangGiao(status: OrderStatus::Confirmed);
        $this->ghnTraVe('delivered');

        app(GhnStatusSync::class)->syncOne($order);

        $order->refresh();

        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
    }

    #[Test]
    public function ghn_bao_huy_hoac_hoan_thi_chi_ghi_lai_chu_khong_tu_huy_don(): void
    {
        /*
         * Huỷ một đơn kéo theo hoàn kho, có thể hoàn tiền, và thường là
         * một cuộc gọi cho khách. Máy quyết định thay là máy quyết chuyện
         * tiền bạc thay người.
         */
        $order = $this->donDangGiao();
        $this->ghnTraVe('returned');

        app(GhnStatusSync::class)->syncOne($order);

        $order->refresh();

        $this->assertSame('returned', $order->shipping_status);
        $this->assertSame(OrderStatus::Shipping, $order->status, 'Không được tự huỷ đơn.');
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
    }

    #[Test]
    public function trang_thai_khong_doi_thi_khong_ghi_gi_va_khong_lam_ban_nhat_ky(): void
    {
        $order = $this->donDangGiao(shippingStatus: 'picked');
        $this->ghnTraVe('picked');

        $this->assertFalse(app(GhnStatusSync::class)->syncOne($order));

        $this->assertSame(
            0,
            ActivityLog::where('action', 'don-hang.dong-bo-van-don')->count(),
            'Ghi lại y nguyên giá trị cũ chỉ làm nhật ký đầy dòng vô nghĩa.',
        );
    }

    #[Test]
    public function moi_lan_doi_trang_thai_deu_ghi_nhat_ky(): void
    {
        // Admin phải trả lời được câu "vì sao đơn này tự chuyển sang đã
        // thanh toán". Không có nhật ký thì tự động hoá trông như lỗi.
        $order = $this->donDangGiao();
        $this->ghnTraVe('delivered');

        app(GhnStatusSync::class)->syncOne($order);

        $this->assertSame(1, ActivityLog::where('action', 'don-hang.dong-bo-van-don')->count());
        $this->assertSame(1, ActivityLog::where('action', 'don-hang.tu-dong-thanh-toan')->count());
    }

    #[Test]
    public function chua_cau_hinh_ghn_thi_khong_goi_di_dau_ca(): void
    {
        config()->set('services.ghn.token', null);
        Http::fake();

        $ketQua = app(GhnStatusSync::class)->syncAll();

        $this->assertSame(['da_hoi' => 0, 'da_doi' => 0, 'loi' => 0], $ketQua);
        Http::assertNothingSent();
    }

    #[Test]
    public function khong_hoi_lai_nhung_van_don_da_di_het_duong(): void
    {
        /*
         * Vận đơn đã `delivered` thì trạng thái không bao giờ đổi nữa.
         * Hỏi lại mỗi 30 phút, mãi mãi, là tiêu hạn mức API cho một câu
         * trả lời đã biết.
         */
        $this->donDangGiao(shippingStatus: 'delivered');
        $this->donDangGiao(shippingStatus: 'cancel');
        $this->donDangGiao(shippingStatus: 'picked');

        $this->ghnTraVe('picked');

        $ketQua = app(GhnStatusSync::class)->syncAll();

        $this->assertSame(1, $ketQua['da_hoi'], 'Chỉ được hỏi vận đơn còn đang chạy.');
    }

    #[Test]
    public function mot_don_hong_khong_lam_dung_ca_luot(): void
    {
        /*
         * Tác vụ chạy nền, không có ai ngồi nhìn. Ném ra ngoài là những
         * đơn còn lại không được đồng bộ, và lần chạy sau lại chết đúng
         * ở đơn đó.
         */
        $this->donDangGiao();
        $this->donDangGiao();

        Http::fake([
            '*/v2/shipping-order/detail' => Http::response(['message' => 'toang'], 500),
        ]);

        $ketQua = app(GhnStatusSync::class)->syncAll();

        $this->assertSame(2, $ketQua['da_hoi']);
        $this->assertSame(2, $ketQua['loi']);
        $this->assertSame(0, $ketQua['da_doi']);
    }
}
