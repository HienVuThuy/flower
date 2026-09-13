<?php

namespace Tests\Feature;

use App\Mail\NewBulkInquiryForShopMail;
use App\Mail\NewOrderForShopMail;
use App\Models\BulkOrderInquiry;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Mail\MailTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Thư báo cho CỬA HÀNG khi có đơn mới và yêu cầu báo giá.
 * ============================================================
 * Trước đây thư duy nhất gửi cửa hàng là khi khách TỰ HUỶ. Đơn mới chỉ lộ
 * ra khi có người mở trang quản trị.
 */
class ThongBaoCuaHangTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL_SHOP = 'cua-hang@angevil.test';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Setting::set('site_email', self::EMAIL_SHOP);
    }

    /** Đếm cả thư gửi thẳng lẫn thư xếp hàng đợi — tuỳ cấu hình mail.queue_outgoing. */
    private function soThu(string $lop, ?callable $loc = null): int
    {
        $loc ??= fn () => true;

        return Mail::sent($lop, $loc)->count() + Mail::queued($lop, $loc)->count();
    }

    /** Phản hồi của lần bấm "Đặt hàng" gần nhất — để kiểm khách có thấy trang lỗi không. */
    private ?\Illuminate\Testing\TestResponse $phanHoiDatHang = null;

    private function datDon(string $thanhToan = 'cod'): ?Order
    {
        $this->actingAs(User::factory()->create());

        $sp = Product::factory()->for(Category::factory())->price('300000.00')->create();
        $this->post('/gio-hang', ['product_id' => $sp->id, 'quantity' => 1]);
        $this->post('/thanh-toan', [
            'recipient_name' => 'Nguyễn Thị Test', 'recipient_phone' => '0987654321',
            'shipping_address' => '1 Đường Test', 'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => $thanhToan, 'address_id' => '',
        ]);
        $this->phanHoiDatHang = $this->post('/thanh-toan/dat-hang');

        return Order::latest('id')->first();
    }

    private function guiBaoGia(): void
    {
        $this->post(route('shop.bulk-inquiry.store'), [
            'contact_name' => 'Chị Lan',
            'contact_phone' => '0912345678',
            'contact_email' => 'lan@khach.test',
            'occasion' => 'Tiệc cưới',
            'event_date' => now()->addDays(10)->toDateString(),
            'quantity_estimate' => 40,
        ])->assertRedirect();
    }

    /* ================= ĐƠN MỚI ================= */

    #[Test]
    public function dat_don_xong_cua_hang_nhan_duoc_thu(): void
    {
        $don = $this->datDon();

        $this->assertNotNull($don, 'Đơn phải được tạo');
        $this->assertSame(1, $this->soThu(
            NewOrderForShopMail::class,
            fn ($thu) => $thu->hasTo(self::EMAIL_SHOP) && $thu->order->is($don),
        ));
    }

    #[Test]
    public function chua_khai_email_cua_hang_thi_KHONG_gui_cho_ai(): void
    {
        // Không đoán một địa chỉ để gửi.
        Setting::set('site_email', null);

        $this->assertNotNull($this->datDon());
        $this->assertSame(0, $this->soThu(NewOrderForShopMail::class));
    }

    #[Test]
    public function gui_thu_hong_thi_DON_VAN_THANH(): void
    {
        /*
         * Lúc gửi thư, đơn đã chốt và kho đã trừ. Để lỗi thư lọt ra ngoài
         * là khách thấy trang lỗi, đặt lại, cửa hàng có hai đơn trùng.
         */
        $this->mock(MailTransport::class, function ($m) {
            $m->shouldReceive('deliver')->andThrow(new \RuntimeException('SMTP sập'));
            $m->shouldReceive('deliversForReal')->andReturn(false);
        });

        $don = $this->datDon();

        $this->assertNotNull($don);
        $this->assertSame(1, Order::count());

        /*
         * ĐẾM ĐƠN THÔI LÀ CHƯA ĐỦ — thử phá code đã chứng minh: đơn được
         * lưu TRƯỚC khi gửi thư, nên để lỗi thư lọt ra ngoài thì vẫn có
         * đúng 1 đơn, chỉ là khách nhìn thấy trang lỗi 500. Phải kiểm
         * chính thứ khách nhận được.
         */
        $this->phanHoiDatHang->assertRedirect();
    }

    #[Test]
    public function thu_don_moi_co_du_thong_tin_de_bat_tay_vao_lam(): void
    {
        $don = $this->datDon();

        $html = (new NewOrderForShopMail($don->load('items')))->render();

        $this->assertStringContainsString($don->order_number, $html);
        $this->assertStringContainsString('0987654321', $html);
        $this->assertStringContainsString(route('admin.orders.show', $don), $html);
        $this->assertStringNotContainsString('Chưa thanh toán MoMo', $html, 'Đơn COD không có cảnh báo MoMo');
    }

    #[Test]
    public function don_momo_chua_tra_thi_thu_dan_CHUA_chuan_bi_hang(): void
    {
        $don = $this->datDon();
        $don->forceFill(['payment_method' => 'momo', 'payment_status' => 'unpaid'])->save();

        $html = (new NewOrderForShopMail($don->fresh('items')))->render();

        $this->assertStringContainsString('Chưa thanh toán MoMo', $html);
    }

    /* ================= YÊU CẦU BÁO GIÁ ================= */

    #[Test]
    public function gui_yeu_cau_bao_gia_cua_hang_nhan_duoc_thu(): void
    {
        $this->guiBaoGia();

        $yc = BulkOrderInquiry::latest('id')->firstOrFail();

        $this->assertSame(1, $this->soThu(
            NewBulkInquiryForShopMail::class,
            fn ($thu) => $thu->hasTo(self::EMAIL_SHOP) && $thu->inquiry->is($yc),
        ));
    }

    #[Test]
    public function thu_bao_gia_dua_ngay_su_kien_len_dau(): void
    {
        $this->guiBaoGia();

        $yc = BulkOrderInquiry::latest('id')->firstOrFail();
        $html = (new NewBulkInquiryForShopMail($yc))->render();

        $this->assertStringContainsString('còn 10 ngày', $html);
        $this->assertStringContainsString('Tiệc cưới', $html);
        $this->assertStringContainsString(route('admin.bulk-inquiries.show', $yc), $html);
    }
}
