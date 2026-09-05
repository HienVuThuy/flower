<?php

namespace Tests\Feature\Checkout;

use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Models\Coupon;
use App\Models\Promotion;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * Danh sách "Chọn mã trong ví" và phần tự chọn mã.
 * ============================================================
 * LUẬT GỐC (QĐ-51): CHỈ MÃ ĐÃ LƯU VÀO VÍ MỚI ĐƯỢC TỰ ÁP.
 *
 * Trang Voucher hứa với khách: "Lưu mã về ví, tới bước thanh toán chọn
 * lại là xong." Mã chưa lưu mà vẫn tự áp thì nút "Lưu mã" và cả khái
 * niệm ví không còn nghĩa gì.
 *
 * Điều thứ hai được canh chừng: DANH SÁCH TRÊN TRANG PHẢI KHỚP VỚI THỨ
 * HỆ THỐNG THẬT SỰ CHỌN TRONG ĐÓ. Lệch một cái là khách thấy một mã lạ
 * hiện trong tổng tiền mà không tìm được ở đâu — đã xảy ra thật (QĐ-47).
 */
class CouponChoiceTest extends CheckoutTestCase
{
    /** Mã chỉ phát trong một trang sự kiện. */
    private function eventCoupon(string $code, string $value = '80000.00'): Coupon
    {
        $promotion = Promotion::create([
            'name' => 'Sự kiện kiểm thử',
            'slug' => 'su-kien-kiem-thu',
            'type' => PromotionType::Percent,
            'value' => '10.00',
            'status' => PromotionStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
        ]);

        return Coupon::factory()->fixed($value)->create([
            'code' => $code,
            'promotion_id' => $promotion->id,
        ]);
    }

    #[Test]
    public function vi_trong_thi_khong_co_ma_nao_duoc_tu_ap(): void
    {
        // ĐÂY LÀ LUẬT GỐC. Bản trước tự áp cả mã công khai chưa lưu, nên
        // trang ghi "Ví voucher đang trống" mà tổng tiền vẫn được giảm —
        // khách không hiểu tiền ở đâu ra, và nút "Lưu mã" thành vô nghĩa.
        $this->shopperWithCart('500000.00');
        Coupon::factory()->fixed('50000.00')->create(['code' => 'CHUALUU']);

        $this->get(self::DETAILS)
            ->assertOk()
            ->assertSee('Ví voucher đang trống');

        $this->assertNull($this->appliedCoupon());
    }

    #[Test]
    public function luu_ma_ve_vi_roi_thi_duoc_tu_ap(): void
    {
        [$user] = $this->shopperWithCart('500000.00');
        $coupon = Coupon::factory()->fixed('50000.00')->create(['code' => 'DALUU']);
        $this->claim($user, $coupon);

        $this->get(self::DETAILS)->assertOk()->assertSee('DALUU');

        $this->assertSame('DALUU', $this->appliedCoupon());
    }

    #[Test]
    public function ma_chua_luu_van_nhap_tay_duoc(): void
    {
        // Chỉ việc TỰ ĐỘNG áp là đòi khách phải nhận mã trước. Gõ mã là
        // hành động rõ ràng của khách, không được chặn — nếu không thì
        // mã in trên tờ rơi cũng vô dụng.
        $this->shopperWithCart('500000.00');
        Coupon::factory()->fixed('50000.00')->create(['code' => 'CHUALUU']);

        $this->get(self::DETAILS);
        $this->assertNull($this->appliedCoupon());

        $this->post(self::COUPON, ['coupon_code' => 'chualuu'])->assertRedirect();
        $this->assertSame('CHUALUU', $this->appliedCoupon());
    }

    #[Test]
    public function khach_vang_lai_khong_co_ma_nao_duoc_tu_chon(): void
    {
        // Không có tài khoản thì không có ví, nên không có gì để tự chọn.
        // Họ vẫn NHẬP TAY được mã ở bước thanh toán — đó là giới hạn thật
        // của việc cho đặt hàng không cần đăng nhập, không phải chỗ để bù
        // bằng cách phát mã cho tất cả.
        //
        // HỎI THẲNG BestCouponFinder chứ không đi qua HTTP: giỏ của khách
        // vãng lai nhận diện bằng session()->getId(), mà TestCase của
        // Laravel không mang cookie phản hồi sang request sau nên mỗi
        // request sinh một phiên mới. Luồng nhiều bước của khách vãng lai
        // phải thử tay — xem docs/KIEM-THU.md.
        Coupon::factory()->fixed('50000.00')->create(['code' => 'CONGKHAI']);

        $ketQua = app(\App\Services\Coupon\BestCouponFinder::class)->find(null, '500000.00');

        $this->assertNull($ketQua);
    }

    #[Test]
    public function ma_dang_duoc_ap_luon_co_mat_trong_danh_sach(): void
    {
        // Ràng buộc gốc: không được tự áp một mã mà khách không tìm thấy.
        [$user] = $this->shopperWithCart('500000.00');
        $this->claim($user, Coupon::factory()->fixed('50000.00')->create(['code' => 'DANGAP']));

        $this->get(self::DETAILS);
        $daAp = $this->appliedCoupon();

        $this->assertNotNull($daAp);
        $this->get(self::DETAILS)->assertSee($daAp);
    }

    #[Test]
    public function khong_tu_ap_ma_cua_su_kien_khi_khach_chua_luu_ve_vi(): void
    {
        // Mã gắn promotion_id chỉ phát trong trang sự kiện — đó là lý do
        // khách chịu bấm vào banner.
        [$user] = $this->shopperWithCart('500000.00');
        $this->eventCoupon('MASUKIEN', '200000.00');
        $this->claim($user, Coupon::factory()->fixed('10000.00')->create(['code' => 'MACHUNG']));

        $this->get(self::DETAILS)->assertDontSee('MASUKIEN');

        $this->assertSame('MACHUNG', $this->appliedCoupon(),
            'Mã sự kiện giảm nhiều hơn nhưng khách chưa lưu, không được tự áp.');
    }

    #[Test]
    public function ma_su_kien_da_luu_ve_vi_thi_duoc_xet_binh_thuong(): void
    {
        // Lưu về ví rồi thì nó là tài sản của khách.
        [$user] = $this->shopperWithCart('500000.00');
        $maSuKien = $this->eventCoupon('MASUKIEN', '200000.00');
        $this->claim($user, $maSuKien);
        $this->claim($user, Coupon::factory()->fixed('10000.00')->create(['code' => 'MACHUNG']));

        $this->get(self::DETAILS)->assertSee('MASUKIEN');
        $this->assertSame('MASUKIEN', $this->appliedCoupon());
    }

    #[Test]
    public function ma_chua_du_dieu_kien_van_hien_kem_ly_do(): void
    {
        // Lọc bỏ cho gọn thì khách lưu mã xong tới đây không thấy nó đâu
        // và kết luận hệ thống nuốt mất mã.
        [$user] = $this->shopperWithCart('500000.00');
        $this->claim($user, Coupon::factory()->fixed('100000.00')->minOrder('900000.00')
            ->create(['code' => 'DONTO']));

        $this->get(self::DETAILS)
            ->assertOk()
            ->assertSee('DONTO')
            ->assertSee('Mã này chỉ áp dụng cho đơn từ 900.000đ.');

        $this->assertNull($this->appliedCoupon());
    }

    #[Test]
    public function chon_ma_tu_danh_sach_van_di_qua_du_phep_kiem_tra(): void
    {
        // Nút trong danh sách gửi wallet_code qua ĐÚNG endpoint mà ô nhập
        // tay dùng. Bấm nút không phải đường tắt bỏ qua kiểm tra.
        [$user] = $this->shopperWithCart('500000.00');
        $this->claim($user, Coupon::factory()->fixed('100000.00')->minOrder('900000.00')
            ->create(['code' => 'DONTO']));

        $this->get(self::DETAILS);
        $this->post(self::COUPON, ['wallet_code' => 'DONTO'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($this->appliedCoupon());
    }

    #[Test]
    public function wallet_code_thang_the_khi_o_nhap_tay_dang_bo_trong(): void
    {
        // Hai ô cùng nằm trong một biểu mẫu. Nếu chúng cùng tên thì PHP
        // lấy ô đứng sau, và bấm một mã trong danh sách có thể hoá thành
        // áp cái mã đang gõ dở ở ô trên.
        [$user] = $this->shopperWithCart('500000.00');
        $this->claim($user, Coupon::factory()->fixed('50000.00')->create(['code' => 'TUDANHSACH']));

        $this->get(self::DETAILS);
        $this->post(self::COUPON, ['wallet_code' => 'TUDANHSACH', 'coupon_code' => '']);

        $this->assertSame('TUDANHSACH', $this->appliedCoupon());
    }

    #[Test]
    public function ma_rieng_khong_luu_ve_vi_duoc_nhung_van_nhap_tay_duoc(): void
    {
        // Mã in trên tờ rơi hoặc gửi riêng cho một khách: liệt kê ra là
        // phát cho tất cả, nhưng chặn nhập tay là vô hiệu hoá cả tờ rơi.
        $this->shopperWithCart('500000.00');
        Coupon::factory()->fixed('70000.00')->private()->create(['code' => 'TOROI']);

        $this->get(self::DETAILS)->assertDontSee('TOROI');
        $this->assertNull($this->appliedCoupon());

        $this->post(self::COUPON, ['coupon_code' => 'TOROI']);
        $this->assertSame('TOROI', $this->appliedCoupon());
    }

    #[Test]
    public function tu_chon_ma_lay_ma_giam_nhieu_nhat_trong_vi(): void
    {
        [$user] = $this->shopperWithCart('500000.00');
        $this->claim($user, Coupon::factory()->fixed('20000.00')->create(['code' => 'ITHON']));
        $this->claim($user, Coupon::factory()->percent('30', '120000.00')->create(['code' => 'NHIEUHON']));

        // 30% của 500.000 = 150.000, bị kẹp còn 120.000 > 20.000.
        $this->get(self::DETAILS);
        $this->assertSame('NHIEUHON', $this->appliedCoupon());
    }

    #[Test]
    public function chon_giup_toi_khi_vi_trong_thi_noi_that_la_khong_co_ma(): void
    {
        // Không được im lặng, cũng không được vơ đại một mã công khai.
        $this->shopperWithCart('500000.00');
        Coupon::factory()->fixed('50000.00')->create(['code' => 'CHUALUU']);

        $this->get(self::DETAILS);
        $this->delete(self::COUPON);

        $this->post(self::COUPON.'/tu-chon')
            ->assertRedirect()
            ->assertSessionHas('info', 'Hiện chưa có mã nào dùng được cho đơn này.');

        $this->assertNull($this->appliedCoupon());
    }
}
