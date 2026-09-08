<?php

namespace Tests\Feature\Order;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Tax\TaxCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Thuế GTGT: TÁCH ra từ tổng, không cộng thêm vào.
 * ============================================================
 * ĐIỀU QUAN TRỌNG NHẤT CẢ TỆP NÀY: bật thuế lên KHÔNG được làm khách
 * phải trả thêm một đồng nào.
 *
 * Giá niêm yết ở Việt Nam đã bao gồm VAT, nên phần thuế được TÁCH RA từ
 * số tiền khách trả:
 *
 *     thuế = tổng − tổng / (1 + thuế suất)
 *
 * Lỗi hay gặp nhất là nhân thẳng: `tổng × thuế suất`. Với 8% thì cách
 * sai cho ra 8.640₫ trên một đơn 108.000₫, còn cách đúng là 8.000₫ —
 * lệch 8% mãi mãi, và chỉ lộ ra ở kỳ quyết toán.
 */
class OrderTaxTest extends TestCase
{
    use RefreshDatabase;

    private function datHang(string $gia = '100000.00'): Order
    {
        $product = Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->price($gia)
            ->stock(20)
            ->create(['weight' => 500]);

        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1]);

        $this->post('/thanh-toan', [
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
        ]);

        $this->post('/thanh-toan/dat-hang');

        return Order::latest('id')->firstOrFail();
    }

    #[Test]
    public function thue_duoc_tach_ra_tu_tong_chu_khong_cong_them_vao(): void
    {
        Setting::set(TaxCalculator::SETTING_KEY, '0.08');

        $order = $this->datHang('100000.00');

        $tong = (float) $order->grand_total;
        $thue = (float) $order->tax_amount;

        /*
         * Phép kiểm THẬT: cộng ngược lại phải ra đúng tổng.
         *
         * (tổng − thuế) × (1 + 8%) = tổng
         *
         * Nếu ai đó sửa thành nhân thẳng thì đẳng thức này vỡ ngay, kể
         * cả khi con số nhìn vẫn "hợp lý".
         */
        $this->assertEqualsWithDelta($tong, ($tong - $thue) * 1.08, 0.02);

        // Và thuế PHẢI nhỏ hơn tổng × 8% — đó là khác biệt giữa hai cách.
        $this->assertLessThan($tong * 0.08, $thue);
    }

    #[Test]
    public function bat_thue_len_khong_lam_khach_phai_tra_them_dong_nao(): void
    {
        /*
         * BÀI QUAN TRỌNG NHẤT. Hai đơn giống hệt nhau, một đơn thuế 0%
         * một đơn thuế 10%, và tổng phải BẰNG NHAU.
         *
         * Vỡ bài này nghĩa là ai đó đã đổi sang mô hình "giá chưa thuế,
         * cộng thuế ở bước cuối" — và khi ấy con số trên thẻ sản phẩm
         * nói dối khách.
         */
        Setting::set(TaxCalculator::SETTING_KEY, '0');
        $khongThue = $this->datHang('250000.00');

        Setting::set(TaxCalculator::SETTING_KEY, '0.10');
        $coThue = $this->datHang('250000.00');

        $this->assertSame(
            $khongThue->grand_total,
            $coThue->grand_total,
            'Bật thuế lên đã làm đổi số tiền khách phải trả.',
        );

        $this->assertSame('0.00', $khongThue->tax_amount);
        $this->assertGreaterThan(0, (float) $coThue->tax_amount);
    }

    #[Test]
    public function thue_suat_duoc_chup_vao_don_de_don_cu_khong_doi_theo(): void
    {
        /*
         * Nhà nước đổi thuế suất thì đơn cũ phải giữ mức đã áp lúc đặt.
         * Tính lại từ mức hiện tại thì báo cáo quý trước tự nhiên khác
         * đi mỗi lần chính sách thay đổi.
         */
        Setting::set(TaxCalculator::SETTING_KEY, '0.08');
        $order = $this->datHang();

        $thueLucDat = $order->tax_amount;

        Setting::set(TaxCalculator::SETTING_KEY, '0.10');

        $order->refresh();

        $this->assertSame($thueLucDat, $order->tax_amount);
        $this->assertSame('0.08000', $order->tax_rate);
    }

    #[Test]
    public function o_thue_suat_bo_trong_khong_bien_thanh_mien_thue(): void
    {
        /*
         * Một ô nhập bị xoá trắng là "chưa điền", KHÔNG phải "miễn thuế".
         * Hiểu nhầm hai thứ này thì cửa hàng ngừng ghi nhận thuế mà
         * không ai bấm gì cả.
         */
        Setting::set(TaxCalculator::SETTING_KEY, null);

        $this->assertSame(
            (string) (float) config('tax.default_rate'),
            app(TaxCalculator::class)->rate(),
        );
    }

    #[Test]
    public function khach_nhin_thay_phan_thue_nam_trong_tong(): void
    {
        /*
         * ĐẢO NGƯỢC MỘT QUYẾT ĐỊNH CŨ — có chủ ý.
         *
         * Bản trước bài này khẳng định điều NGƯỢC LẠI: khách không được
         * nhìn thấy con số thuế, vì lúc đó thuế chỉ là số liệu nội bộ để
         * admin đối soát.
         *
         * Nay cửa hàng đã có dữ liệu hoá đơn GTGT, mà hoá đơn thì bắt
         * buộc ghi giá chưa thuế, thuế suất và tiền thuế. Khách phải đối
         * chiếu được đơn của mình với hoá đơn họ nhận; giấu con số đi
         * làm hai chứng từ có vẻ nói hai chuyện khác nhau.
         *
         * ĐIỀU KHÔNG ĐỔI: con số này nằm DƯỚI dòng tổng và mang chữ
         * "Trong đó" — nó là phần nằm trong tổng, không phải khoản cộng
         * thêm. Bài kế bên canh đúng điều đó.
         */
        Setting::set(TaxCalculator::SETTING_KEY, '0.08');
        $order = $this->datHang();

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertSee('Trong đó thuế GTGT')
            ->assertSee('8%');
    }

    #[Test]
    public function admin_nhin_thay_thue_kem_muc_da_ap(): void
    {
        Setting::set(TaxCalculator::SETTING_KEY, '0.08');
        $order = $this->datHang();

        $admin = User::factory()->create();
        $admin->role = \App\Enums\UserRole::Admin;
        $admin->save();

        $this->actingAs($admin)
            ->get('/admin/orders/' . $order->order_number)
            ->assertOk()
            ->assertSee('Trong đó thuế VAT')
            // Mức đã áp phải hiện ra: kiểm toán cần biết mức nào, không
            // phải chỉ số tiền.
            ->assertSee('8%');
    }

    #[Test]
    public function don_cu_khong_co_so_lieu_thue_thi_khong_hien_gi(): void
    {
        /*
         * NULL đọc ra là "không có số liệu"; 0 đọc ra là "thuế bằng
         * không". Hiện "0₫" cho một đơn đặt trước khi hệ thống tính thuế
         * là nói rằng đơn đó miễn thuế — sai hẳn.
         */
        Setting::set(TaxCalculator::SETTING_KEY, '0.08');
        $order = $this->datHang();

        $order->forceFill(['tax_rate' => null, 'tax_amount' => null])->saveQuietly();

        $admin = User::factory()->create();
        $admin->role = \App\Enums\UserRole::Admin;
        $admin->save();

        $this->actingAs($admin)
            ->get('/admin/orders/' . $order->order_number)
            ->assertOk()
            ->assertDontSee('Trong đó thuế VAT');
    }
}
