<?php

namespace Tests\Feature\Order;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\TaxClass;
use App\Models\User;
use App\Services\Tax\TaxCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * VAT TÍNH THEO TỪNG DÒNG HÀNG, không tính trên tổng đơn.
 * ============================================================
 * VẤN ĐỀ ĐÃ SỬA: cả cửa hàng dùng CHUNG MỘT thuế suất.
 *
 * Cửa hàng này bán hoa tươi, cây giống, chậu sứ và giá thể — bốn thứ có
 * bản chất thuế khác nhau. Một con số duy nhất ép cả bốn vào cùng một
 * mức, và mức nào cũng sai với ba loại còn lại:
 *
 *     Bó hoa 300.000₫   (không chịu VAT)
 *     Chậu sứ 200.000₫  (VAT 10%)
 *     -------------------------------------------------
 *     Tính trên tổng 8%:  500.000 -> 37.037₫    ✗ sai cả hai vế
 *     Tính theo dòng:       0 + 18.182 = 18.182₫  ✓
 *
 * Con số sai KHÔNG làm gãy trang nào — nó chỉ lặng lẽ đi vào sổ kế toán,
 * và chỉ lộ ra ở kỳ quyết toán. Vì thế mỗi bất biến ở đây tương ứng với
 * đúng một cách hỏng:
 *
 *   1. Sản phẩm mang mức của nhóm thuế nó thuộc về.
 *   2. Chưa phân loại thì LÙI VỀ mức cửa hàng, không thành miễn thuế.
 *   3. "Không chịu VAT" ghi NULL, khác hẳn "chịu 0%".
 *   4. Đẳng thức đối soát: đơn = tổng các dòng + phần phí vận chuyển.
 *   5. Mã giảm giá làm GIẢM tiền chịu thuế, và được chia hết cho các dòng.
 *   6. Đổi phân loại sau khi bán KHÔNG viết lại đơn cũ.
 *   7. Đổi mức thuế KHÔNG làm khách phải trả thêm đồng nào.
 */
class VatPerProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mức của cửa hàng, dùng cho hàng chưa phân loại và cho phí giao.
        Setting::set(TaxCalculator::SETTING_KEY, '0.08');
    }

    private function nhom(string $code, ?string $rate): TaxClass
    {
        return TaxClass::create([
            'code' => $code,
            'name' => 'Nhóm ' . $code,
            'rate' => $rate,
            'is_active' => true,
        ]);
    }

    private function hang(string $ten, string $gia, ?TaxClass $nhom = null): Product
    {
        return Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->price($gia)
            ->stock(50)
            ->create([
                'name' => $ten,
                'weight' => 500,
                'tax_class_id' => $nhom?->id,
            ]);
    }

    /**
     * Đặt hàng qua ĐÚNG ĐƯỜNG KHÁCH ĐI, không gọi thẳng service.
     *
     * Ba lỗi gần nhất của luồng này đều nằm ở chỗ ghép nối — session giữ
     * gì, biểu mẫu gửi gì — chứ không nằm trong phép tính. Gọi thẳng
     * OrderService thì cả ba vẫn xanh.
     *
     * @param  array<int, array{0: Product, 1?: int}>  $gio
     */
    private function datHang(array $gio, array $themVaoForm = []): Order
    {
        $this->actingAs(User::factory()->create());

        foreach ($gio as $mon) {
            $this->post('/gio-hang', [
                'product_id' => $mon[0]->id,
                'quantity' => $mon[1] ?? 1,
            ]);
        }

        $this->post('/thanh-toan', array_merge([
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
        ], $themVaoForm));

        $this->post('/thanh-toan/dat-hang');

        return Order::latest('id')->firstOrFail()->load('items');
    }

    /* ================= 1. MỖI SẢN PHẨM MANG MỨC CỦA NÓ ================= */

    #[Test]
    public function san_pham_mang_thue_suat_cua_nhom_no_thuoc_ve(): void
    {
        $order = $this->datHang([[$this->hang('Chậu sứ', '110000.00', $this->nhom('vat_10', '0.10000'))]]);

        $dong = $order->items->first();

        $this->assertSame('0.10000', $dong->tax_rate, 'Dòng hàng không mang mức của nhóm thuế.');

        /*
         * TÁCH NGƯỢC, không nhân thẳng. 110.000 đã gồm 10% thì phần thuế
         * là 10.000₫ (= 110.000 − 110.000/1,1), KHÔNG phải 11.000₫.
         */
        $this->assertSame('10000.00', $dong->tax_amount);
    }

    #[Test]
    public function chua_phan_loai_thi_lui_ve_muc_cua_hang_chu_khong_thanh_mien_thue(): void
    {
        /*
         * `tax_class_id` NULL là "chưa điền", KHÔNG phải "miễn thuế".
         *
         * Hiểu nhầm hai thứ này thì MỌI sản phẩm đang có bỗng nhiên
         * không chịu thuế ngay khi bảng nhóm thuế ra đời — một thay đổi
         * kế toán khổng lồ mà không ai bấm nút nào.
         */
        $order = $this->datHang([[$this->hang('Cây chưa phân loại', '108000.00')]]);

        $dong = $order->items->first();

        $this->assertSame('0.08000', $dong->tax_rate);
        $this->assertNotNull($dong->tax_amount);
        $this->assertGreaterThan(0, (float) $dong->tax_amount);
    }

    #[Test]
    public function nhom_thue_da_tat_thi_cung_lui_ve_muc_cua_hang(): void
    {
        /*
         * Nhóm tắt vẫn phải giữ lại vì đơn cũ trỏ tới nó, nhưng không
         * được dùng cho đơn mới. Nếu vẫn dùng, admin tắt một nhóm mà
         * không có gì đổi — và họ sẽ tắt tiếp cái khác, tưởng nút hỏng.
         */
        $nhom = $this->nhom('vat_10', '0.10000');
        $nhom->update(['is_active' => false]);

        $order = $this->datHang([[$this->hang('Chậu sứ', '110000.00', $nhom)]]);

        $this->assertSame('0.08000', $order->items->first()->tax_rate);
    }

    /* ================= 2. "KHÔNG CHỊU VAT" KHÁC "CHỊU 0%" ================= */

    #[Test]
    public function hang_khong_chiu_VAT_ghi_NULL_chu_khong_ghi_0(): void
    {
        /*
         * NULL đọc ra là "không thuộc đối tượng chịu VAT"; 0 đọc ra là
         * "chịu thuế suất 0%". Trên hoá đơn hai trường hợp này ghi khác
         * nhau, nên gộp lại là làm mất một phân biệt nghiệp vụ thật.
         */
        $order = $this->datHang([[$this->hang('Bó hoa', '300000.00', $this->nhom('vat_exempt', null))]]);

        $dong = $order->items->first();

        $this->assertNull($dong->tax_rate, 'Hàng không chịu VAT bị ghi thành thuế suất 0%.');
        $this->assertNull($dong->tax_amount);
    }

    #[Test]
    public function chiu_0_phan_tram_van_la_hang_chiu_thue(): void
    {
        // Vế còn lại của bài trên: 0 phải ghi ra 0, không được thành NULL.
        $order = $this->datHang([[$this->hang('Hàng xuất khẩu', '300000.00', $this->nhom('vat_0', '0.00000'))]]);

        $dong = $order->items->first();

        $this->assertSame('0.00000', $dong->tax_rate);
        $this->assertSame('0.00', $dong->tax_amount);
    }

    /* ================= 3. ĐƠN HỖN HỢP ================= */

    #[Test]
    public function don_hon_hop_tinh_theo_tung_dong_chu_khong_theo_tong(): void
    {
        /*
         * BÀI QUAN TRỌNG NHẤT CẢ TỆP.
         *
         * Bó hoa không chịu VAT + chậu sứ chịu 10%. Nếu ai đó quay lại
         * cách cũ (một phép trên tổng), con số sẽ khác hẳn — và không có
         * trang nào gãy để báo.
         */
        $hoa = $this->hang('Bó hoa', '300000.00', $this->nhom('vat_exempt', null));
        $chau = $this->hang('Chậu sứ', '220000.00', $this->nhom('vat_10', '0.10000'));

        $order = $this->datHang([[$hoa], [$chau]]);

        $theoDong = $order->items->sum(fn ($i) => (float) $i->tax_amount);

        // Chỉ chậu sứ đóng thuế: 220.000 − 220.000/1,1 = 20.000₫.
        $this->assertEqualsWithDelta(20000.0, $theoDong, 0.01);

        /*
         * Và nó phải KHÁC HẲN cách tính cũ trên tổng tiền hàng. Không
         * khẳng định điều này thì bài vẫn xanh khi mã quay về cách cũ ở
         * một giỏ mà hai cách tình cờ cho kết quả gần nhau.
         */
        $cachCu = 520000.0 - 520000.0 / 1.08;
        $this->assertGreaterThan(1000.0, abs($cachCu - $theoDong));
    }

    /* ================= 4. ĐẲNG THỨC ĐỐI SOÁT ================= */

    #[Test]
    public function thue_cua_don_bang_tong_cac_dong_cong_thue_phi_van_chuyen(): void
    {
        /*
         * Kế toán đối chiếu bằng đúng đẳng thức này. Lệch một đồng là
         * lệch, và không ai giải thích được phần chênh.
         *
         * Đây cũng là bài canh cột `shipping_tax_amount`: gộp thuế phí
         * giao vào tổng mà không tách riêng thì vế phải luôn thiếu.
         */
        $order = $this->datHang([
            [$this->hang('Chậu sứ', '220000.00', $this->nhom('vat_10', '0.10000'))],
            [$this->hang('Cây chưa phân loại', '108000.00')],
        ]);

        $tongDong = '0.00';

        foreach ($order->items as $dong) {
            $tongDong = bcadd($tongDong, (string) ($dong->tax_amount ?? '0.00'), 2);
        }

        $this->assertSame(
            (string) $order->tax_amount,
            bcadd($tongDong, (string) $order->shipping_tax_amount, 2),
            'Thuế đầu đơn không khớp với tổng thuế các dòng cộng thuế phí giao.',
        );
    }

    #[Test]
    public function phi_van_chuyen_cung_co_phan_thue_cua_no(): void
    {
        /*
         * Vận chuyển là một DỊCH VỤ, nó chịu thuế. Bỏ qua phần này là
         * ghi thiếu thuế trên mọi đơn có phí giao — âm thầm, và đúng
         * bằng một tỉ lệ cố định nên rất khó phát hiện bằng mắt.
         */
        $order = $this->datHang([[$this->hang('Cây nhỏ', '50000.00')]]);

        $this->assertGreaterThan(0, (float) $order->shipping_fee, 'Đơn này lẽ ra phải có phí giao.');
        $this->assertNotNull($order->shipping_tax_amount);
        $this->assertGreaterThan(0, (float) $order->shipping_tax_amount);
    }

    /* ================= 5. MÃ GIẢM GIÁ ================= */

    #[Test]
    public function ma_giam_gia_duoc_chia_het_cho_cac_dong(): void
    {
        /*
         * Mã áp cho cả đơn, thuế tính theo từng dòng — nên phần giảm
         * phải chia cho các dòng, và chia HẾT.
         *
         * Làm tròn từng dòng rồi cộng thì tổng không bằng số đã giảm, và
         * khi ấy SUM(items.discount_amount) != orders.coupon_discount.
         * Dòng cuối gánh phần lẻ chính là để đẳng thức này luôn khít.
         */
        Coupon::factory()->fixed('100000.00')->create(['code' => 'GIAM100K']);

        /*
         * BA DÒNG BẰNG NHAU, và số tiền giảm KHÔNG chia hết cho ba.
         *
         * Chọn con số này có chủ ý: 100.000 / 3 = 33.333,33…₫ nên phép
         * chia để lại đúng 0,01₫ lẻ. Với một giỏ chia hết thì bài vẫn
         * xanh kể cả khi bỏ hẳn luật "dòng cuối gánh phần lẻ" — tức là
         * bài không đo gì cả. Đã kiểm bằng cách phá luật đó.
         */
        $order = $this->datHang([
            [$this->hang('Món A', '100000.00')],
            [$this->hang('Món B', '100000.00')],
            [$this->hang('Món C', '100000.00')],
        ], ['coupon_code' => 'GIAM100K']);

        $this->assertSame('100000.00', (string) $order->coupon_discount, 'Mã chưa được áp.');

        $tongChia = '0.00';

        foreach ($order->items as $dong) {
            $tongChia = bcadd($tongChia, (string) $dong->discount_amount, 2);
        }

        $this->assertSame(
            (string) $order->coupon_discount,
            $tongChia,
            'Phần giảm chia cho các dòng không cộng lại đúng bằng số tiền đã giảm.',
        );

        // Và phần lẻ phải nằm ở DÒNG CUỐI, không rơi vãi đâu đó.
        $this->assertSame('33333.33', (string) $order->items[0]->discount_amount);
        $this->assertSame('33333.34', (string) $order->items->last()->discount_amount);
    }

    #[Test]
    public function ma_giam_gia_lam_GIAM_tien_chiu_thue(): void
    {
        /*
         * THỨ TỰ QUAN TRỌNG: trừ mã trước, tách thuế sau.
         *
         * Tách thuế trên giá gốc rồi mới trừ mã là ghi nhiều thuế hơn số
         * cửa hàng thật sự thu được — và cửa hàng nộp thừa phần chênh đó.
         */
        Coupon::factory()->fixed('110000.00')->create(['code' => 'GIAM110K']);

        $hang = fn () => $this->hang('Chậu sứ ' . uniqid(), '1100000.00', TaxClass::firstWhere('code', 'vat_10'));

        $this->nhom('vat_10', '0.10000');

        $khongMa = $this->datHang([[$hang()]]);
        $thueKhongMa = (float) $khongMa->items->first()->tax_amount;

        $coMa = $this->datHang([[$hang()]], ['coupon_code' => 'GIAM110K']);
        $thueCoMa = (float) $coMa->items->first()->tax_amount;

        // 1.100.000 -> 100.000₫ thuế. Giảm 110.000 -> 990.000 -> 90.000₫.
        $this->assertEqualsWithDelta(100000.0, $thueKhongMa, 0.01);
        $this->assertEqualsWithDelta(90000.0, $thueCoMa, 0.01);
    }

    /* ================= 6. BẢN CHỤP ================= */

    #[Test]
    public function doi_phan_loai_sau_khi_ban_KHONG_viet_lai_don_cu(): void
    {
        $nhom = $this->nhom('vat_10', '0.10000');
        $sanPham = $this->hang('Chậu sứ', '110000.00', $nhom);

        $order = $this->datHang([[$sanPham]]);
        $thueLucBan = $order->items->first()->tax_amount;

        // Nhà nước đổi mức, hoặc kế toán phân loại lại.
        $nhom->update(['rate' => '0.05000']);
        $sanPham->update(['tax_class_id' => null]);

        $order->refresh()->load('items');

        $this->assertSame($thueLucBan, $order->items->first()->tax_amount);
        $this->assertSame('0.10000', $order->items->first()->tax_rate);
    }

    /* ================= 7. GIÁ ĐÃ GỒM VAT ================= */

    #[Test]
    public function doi_nhom_thue_KHONG_lam_khach_phai_tra_them_dong_nao(): void
    {
        /*
         * BẤT BIẾN CỦA CẢ MÔ HÌNH. Giá niêm yết đã bao gồm VAT, nên thuế
         * được TÁCH RA khỏi tổng chứ không cộng thêm vào.
         *
         * Vỡ bài này nghĩa là ai đó đã đổi sang mô hình "giá chưa thuế,
         * cộng thuế ở bước cuối" — và khi ấy con số trên thẻ sản phẩm
         * nói dối khách.
         */
        $khongThue = $this->datHang([[$this->hang('Cây A', '500000.00', $this->nhom('vat_exempt', null))]]);
        $muoiPhanTram = $this->datHang([[$this->hang('Cây B', '500000.00', $this->nhom('vat_10', '0.10000'))]]);

        $this->assertSame(
            (string) $khongThue->grand_total,
            (string) $muoiPhanTram->grand_total,
            'Đổi nhóm thuế đã làm đổi số tiền khách phải trả.',
        );
    }

    /* ================= 8. HIỂN THỊ ================= */

    #[Test]
    public function trang_don_hang_tach_thue_theo_tung_muc_khi_don_hon_hop(): void
    {
        $order = $this->datHang([
            [$this->hang('Bó hoa', '300000.00', $this->nhom('vat_exempt', null))],
            [$this->hang('Chậu sứ', '220000.00', $this->nhom('vat_10', '0.10000'))],
        ]);

        $this->get('/don-hang/' . $order->order_number)
            ->assertOk()
            ->assertSee('Trong đó thuế GTGT')
            // Đơn mang hai mức, nên bảng tách phải hiện cả hai.
            ->assertSee('10%')
            ->assertSee('Không chịu VAT');
    }
}
