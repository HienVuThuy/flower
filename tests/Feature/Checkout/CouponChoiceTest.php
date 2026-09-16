<?php

namespace Tests\Feature\Checkout;

use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Models\Coupon;
use App\Models\Promotion;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

/** Danh sách "Chọn mã trong ví" và phần tự chọn mã. */
class CouponChoiceTest extends CheckoutTestCase
{
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
        Coupon::factory()->fixed('50000.00')->create(['code' => 'CONGKHAI']);

        $ketQua = app(\App\Services\Coupon\BestCouponFinder::class)->find(null, '500000.00');

        $this->assertNull($ketQua);
    }

    #[Test]
    public function ma_dang_duoc_ap_luon_co_mat_trong_danh_sach(): void
    {
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
        [$user] = $this->shopperWithCart('500000.00');
        $this->claim($user, Coupon::factory()->fixed('50000.00')->create(['code' => 'TUDANHSACH']));

        $this->get(self::DETAILS);
        $this->post(self::COUPON, ['wallet_code' => 'TUDANHSACH', 'coupon_code' => '']);

        $this->assertSame('TUDANHSACH', $this->appliedCoupon());
    }

    #[Test]
    public function ma_rieng_khong_luu_ve_vi_duoc_nhung_van_nhap_tay_duoc(): void
    {
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

        $this->get(self::DETAILS);
        $this->assertSame('NHIEUHON', $this->appliedCoupon());
    }

    #[Test]
    public function chon_giup_toi_khi_vi_trong_thi_noi_that_la_khong_co_ma(): void
    {
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
