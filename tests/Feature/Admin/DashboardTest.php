<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\Shop\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Số liệu kinh doanh ở trang Tổng quan.
 * ============================================================
 * ĐIỀU ĐƯỢC CANH CHỪNG Ở ĐÂY: trang Tổng quan và trang Phân tích phải
 * nói CÙNG MỘT CON SỐ cho cùng một câu hỏi.
 *
 * Bản trước, DashboardController tự viết lấy một hàm orderStats() với
 * định nghĩa doanh thu của riêng nó. Hai định nghĩa cho cùng một chỉ số
 * là chuyện chỉ chờ ngày lệch nhau: sửa cách tính ở một trang thì trang
 * kia vẫn nói con số cũ, và không có gì trên màn hình cho thấy hai nơi
 * đang bất đồng. Người đọc tin cả hai.
 *
 * Bài kiểm thử dựng lại đúng tình huống đó: dựng dữ liệu, mở CẢ HAI
 * trang, và đòi hai màn hình in ra cùng một chuỗi tiền.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    /**
     * Một đơn với trạng thái và thời điểm cho trước.
     *
     * forceFill cho `status`, `payment_status` và `created_at`: cả ba cố
     * ý không nằm trong $fillable. Đưa vào create() thì Laravel bỏ qua
     * im lặng và đơn ở lại mặc định — bài kiểm thử xanh vì lý do sai.
     */
    private function order(OrderStatus $status, string $tien, int $ngayTruoc = 1): Order
    {
        $order = Order::create([
            'order_number' => 'FP-DB-'.strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'subtotal' => $tien,
            'discount_total' => '0.00',
            'shipping_fee' => '0.00',
            'coupon_discount' => '0.00',
            'grand_total' => $tien,
        ]);

        $order->forceFill([
            'status' => $status,
            'payment_status' => PaymentStatus::Paid,
            'created_at' => now()->subDays($ngayTruoc),
        ])->save();

        return $order;
    }

    private function xem(string $url)
    {
        return $this->actingAs($this->admin())->get($url);
    }

    /* ================= MỘT ĐỊNH NGHĨA, HAI MÀN HÌNH ================= */

    #[Test]
    public function tong_quan_va_phan_tich_noi_cung_mot_con_so_doanh_thu(): void
    {
        $this->order(OrderStatus::Completed, '1200000.00', ngayTruoc: 2);
        $this->order(OrderStatus::Completed, '800000.00', ngayTruoc: 3);

        // Hai đơn này KHÔNG phải doanh thu: một đơn chưa giao xong, một
        // đơn đã huỷ. Nếu một trong hai trang cộng nhầm, hai chuỗi tiền
        // sẽ khác nhau và bài này đỏ.
        $this->order(OrderStatus::Pending, '5000000.00', ngayTruoc: 1);
        $this->order(OrderStatus::Cancelled, '9000000.00', ngayTruoc: 1);

        $mong = Money::format('2000000');

        $this->xem('/admin/dashboard?ky=30')->assertOk()->assertSee($mong);
        $this->xem('/admin/phan-tich?ky=30')->assertOk()->assertSee($mong);
    }

    #[Test]
    public function doanh_thu_chi_tinh_don_da_giao(): void
    {
        $this->order(OrderStatus::Shipping, '700000.00');
        $this->order(OrderStatus::Completed, '300000.00');

        $this->xem('/admin/dashboard?ky=30')
            ->assertOk()
            ->assertSee(Money::format('300000'))
            ->assertDontSee(Money::format('1000000'));
    }

    /* ================= KỲ ĐANG CHỌN ================= */

    #[Test]
    public function doi_ky_thi_doi_cua_so_thoi_gian(): void
    {
        /*
         * Đơn 20 ngày trước nằm trong "30 ngày qua" nhưng KHÔNG nằm
         * trong "7 ngày qua". Ô chọn kỳ mà không thật sự lọc thì nó chỉ
         * là ba cái nút đổi màu.
         *
         * SOI VÀO TỔNG, không soi vào tiền của một đơn: khối "Đơn gần
         * đây" cố ý KHÔNG cắt theo kỳ (nó trả lời "vừa có gì xảy ra"),
         * nên tiền của từng đơn vẫn hiện ở mọi kỳ. Chỉ con số CỘNG LẠI
         * mới thuộc về ô chọn kỳ.
         */
        $this->order(OrderStatus::Completed, '450000.00', ngayTruoc: 20);
        $this->order(OrderStatus::Completed, '450000.00', ngayTruoc: 21);

        $this->xem('/admin/dashboard?ky=30')->assertSee(Money::format('900000'));
        $this->xem('/admin/dashboard?ky=7')->assertDontSee(Money::format('900000'));
    }

    #[Test]
    public function nut_ky_dang_chon_duoc_to_dam_o_moi_trang_co_o_chon_ky(): void
    {
        /*
         * PHP TỰ ĐỔI KHOÁ MẢNG '30' THÀNH SỐ NGUYÊN 30.
         *
         * Hằng PERIODS khai `'30' => '30 ngày qua'`, nhưng khi lặp thì
         * $value là int 30, còn kỳ đọc từ URL là chuỗi '30'. So bằng ===
         * thì LUÔN SAI: đo trên trang thật, không nút nào được tô đậm ở
         * cả Tổng quan, Phân tích lẫn Tồn kho — người dùng không biết
         * mình đang xem kỳ nào. Riêng 'Toàn bộ' vẫn đúng vì khoá 'all'
         * không phải chữ số, nên lỗi chỉ lộ ra ở hai trong ba nút.
         */
        $trang = [
            '/admin/dashboard?ky=7' => '7 ngày qua',
            '/admin/phan-tich?ky=30' => '30 ngày qua',
            '/admin/ton-kho?ky=90' => '90 ngày qua',
        ];

        foreach ($trang as $url => $nhan) {
            $html = $this->xem($url)->assertOk()->getContent();

            $this->assertMatchesRegularExpression(
                '#class="btn btn-sm btn-primary-brand">\s*' . preg_quote($nhan, '#') . '\s*</a>#u',
                $html,
                "Trang {$url} không tô đậm nút \"{$nhan}\".",
            );
        }
    }

    #[Test]
    public function form_xuat_du_lieu_tich_san_dung_ky_dang_xem(): void
    {
        $html = $this->xem('/admin/phan-tich/xuat?ky=7')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#value="7"\s+checked#', $html);
    }

    #[Test]
    public function moc_ky_truoc_in_theo_kieu_viet_va_co_don_vi_tien(): void
    {
        /*
         * Bản trước in "so với 13,810,000 kỳ trước" — kiểu Anh, không
         * đơn vị — ngay dưới con số chính "7.450.000₫". Cùng một thẻ hai
         * quy ước, và "13,810" đọc theo kiểu Việt là mười ba phẩy tám.
         */
        $this->order(OrderStatus::Completed, '1000000.00', ngayTruoc: 2);
        $this->order(OrderStatus::Completed, '13810000.00', ngayTruoc: 40);

        $this->xem('/admin/dashboard?ky=30')
            ->assertOk()
            ->assertSee('so với ' . Money::format('13810000') . ' kỳ trước')
            ->assertDontSee('13,810,000');
    }

    #[Test]
    public function moc_ky_truoc_cua_so_dem_cung_in_kieu_viet(): void
    {
        /*
         * Nhánh số đếm (lượt xem, số phiên ở phễu). Cần mốc từ 1.000 trở
         * lên mới lộ dấu ngăn nghìn, mà dựng hàng nghìn đơn chỉ để thấy
         * một dấu chấm thì quá nặng — dựng thẳng component.
         */
        $this->blade('<x-admin.trend :now="2400" :before="1500" />')
            ->assertSee('so với 1.500 kỳ trước')
            ->assertSee('60,0%');
    }

    #[Test]
    public function tham_so_ky_la_thi_lui_ve_mac_dinh_chu_khong_no(): void
    {
        // `?ky=<bất kỳ thứ gì>` là thứ ai cũng gõ được vào thanh địa chỉ.
        $this->xem('/admin/dashboard?ky=khong-ton-tai')
            ->assertOk()
            ->assertSee('30 ngày qua');
    }

    #[Test]
    public function ky_toan_bo_KHONG_hien_phan_so_sanh(): void
    {
        /*
         * Không có gì nằm trước "toàn bộ". Bịa ra một mốc để có cái mà
         * so là in một con số phần trăm không dựa trên gì cả.
         */
        $this->order(OrderStatus::Completed, '150000.00', ngayTruoc: 400);

        $this->xem('/admin/dashboard?ky=all')
            ->assertOk()
            ->assertSee(Money::format('150000'))
            ->assertDontSee('so với');
    }

    /* ================= NULL KHÁC 0 ================= */

    #[Test]
    public function chua_co_don_da_giao_thi_noi_chua_tinh_duoc_chu_khong_in_0d(): void
    {
        /*
         * Giá trị đơn trung bình = doanh thu / số đơn đã giao. Chưa có
         * đơn nào thì mẫu số bằng 0 và KHÔNG CÓ giá trị trung bình.
         *
         * In "0₫" ở đó đọc ra như "khách mua mà không trả đồng nào" —
         * một câu hoàn toàn khác với "chưa có gì để tính".
         */
        $this->order(OrderStatus::Pending, '400000.00');

        $this->xem('/admin/dashboard?ky=30')
            ->assertOk()
            ->assertSee('chưa tính được');
    }

    /* ================= ĐIỀU HƯỚNG ================= */

    #[Test]
    public function moi_lien_ket_tren_trang_deu_dung_dieu_huong_khong_tai_lai(): void
    {
        /*
         * Cả trang quản trị đổi nội dung tại chỗ qua `data-admin-link`.
         * Bản trước trang Tổng quan dùng `href` trần, nên đây là màn
         * hình DUY NHẤT còn nạp lại toàn trang mỗi lần bấm — một sự
         * khác biệt không ai cố ý tạo ra và không ai để ý.
         */
        $html = $this->xem('/admin/dashboard')->assertOk()->getContent();

        preg_match_all('#<a\s([^>]*href="[^"]*/admin/[^"]*"[^>]*)>#', $html, $khop);

        $thieu = array_values(array_filter(
            $khop[1],
            fn (string $the) => ! str_contains($the, 'data-admin-link'),
        ));

        $this->assertSame([], $thieu, 'Còn liên kết admin chưa gắn data-admin-link.');
    }

    /* ================= KHÔNG CÒN SỐ ĐẾM VÔ NGHĨA ================= */

    #[Test]
    public function khong_con_bon_the_dem_danh_muc_san_pham_khach_hang(): void
    {
        /*
         * Bốn con số đó gần như không đổi từ ngày này sang ngày khác và
         * không trả lời câu hỏi nào của người mở trang. Chúng chiếm đúng
         * chỗ dễ nhìn nhất, nên người ta học cách lướt qua cả vùng đó —
         * kể cả khi vùng đó về sau có thứ đáng đọc.
         */
        /*
         * Soi vào CLASS `stat-card` chứ không vào chữ "Danh mục": chữ đó
         * vẫn nằm ở thanh điều hướng bên trái, đúng chỗ của nó. Bốn thẻ
         * kia là thứ phải biến mất khỏi vùng nội dung.
         */
        $this->xem('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('stat-card', escape: false);
    }

    #[Test]
    public function trang_noi_ro_so_lieu_tinh_luc_may_gio(): void
    {
        /*
         * Một bảng điều khiển mở từ sáng trông y hệt một bảng vừa tải
         * xong. Số liệu đứng im cả buổi mà không có gì cho biết, nên
         * người xem tin rằng "hôm nay chưa có đơn nào" trong khi thật ra
         * trang đã cũ ba tiếng.
         *
         * Mốc giờ do MÁY CHỦ in ra, không phải JavaScript vẽ thêm: tắt
         * script thì vẫn phải biết con số mình đang nhìn cũ tới đâu.
         */
        $html = $this->xem('/admin/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Số liệu lúc', $html);
        $this->assertStringContainsString('Làm mới', $html);

        // Mốc phải là giờ Việt Nam, như mọi mốc khác trên trang (QĐ-245).
        $this->assertStringContainsString('+07:00', $html);
    }
}
