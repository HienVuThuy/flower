<?php

namespace Tests\Feature\Checkout;

use App\Models\Coupon;
use PHPUnit\Framework\Attributes\Test;

/**
 * Nút "Bỏ mã" ở bước thanh toán.
 * ============================================================
 * MỌI BÀI Ở ĐÂY ĐỀU LÀ MỘT LỖI ĐÃ XẢY RA THẬT, không phải giả định.
 * Xem QĐ-44 và QĐ-45 trong docs/DOMAIN-DECISIONS.md.
 */
class CouponRemovalTest extends CheckoutTestCase
{
    #[Test]
    public function bo_ma_roi_tai_lai_trang_thi_ma_khong_quay_lai(): void
    {
        // ĐÂY LÀ LỖI CHÍNH. removeCoupon() gọi clearCoupon(), xoá cả mã
        // lẫn cờ `auto`, nên session trông y hệt lúc khách chưa có mã và
        // autoApplyBestCoupon() áp lại đúng cái vừa bỏ. Nút "Bỏ mã" khi
        // đó không bao giờ hoạt động.
        [$user] = $this->shopperWithCart('500000.00');
        $this->claim($user, Coupon::factory()->fixed('50000.00')->create(['code' => 'GIAM50K']));

        $this->get(self::DETAILS);
        $this->assertSame('GIAM50K', $this->appliedCoupon(), 'Mã phải được tự áp trước đã.');

        $this->delete(self::COUPON)->assertRedirect();
        $this->assertNull($this->appliedCoupon());

        // Phần quan trọng: MỞ LẠI TRANG.
        $this->get(self::DETAILS)->assertOk();
        $this->assertNull($this->appliedCoupon(), 'Mã đã bỏ không được tự áp lại.');
    }

    #[Test]
    public function bo_ma_nhieu_lan_van_khong_lam_ma_quay_lai(): void
    {
        [$user] = $this->shopperWithCart('500000.00');
        $this->claim($user, Coupon::factory()->fixed('50000.00')->create(['code' => 'GIAM50K']));

        $this->get(self::DETAILS);

        foreach (range(1, 3) as $lan) {
            $this->delete(self::COUPON);
            $this->get(self::DETAILS);
            $this->assertNull($this->appliedCoupon(), "Lần bỏ thứ {$lan} phải có tác dụng.");
        }
    }

    #[Test]
    public function bo_ma_khong_lam_mat_thong_tin_dang_nhap_do(): void
    {
        // Ô mã giảm giá từng là biểu mẫu riêng, nên bấm "Bỏ mã" giữa
        // chừng là mất sạch tên, số điện thoại, địa chỉ vừa gõ — trông
        // đúng như đơn hàng vừa bị huỷ.
        [$user] = $this->shopperWithCart('500000.00');
        $this->claim($user, Coupon::factory()->fixed('50000.00')->create(['code' => 'GIAM50K']));
        $this->get(self::DETAILS);

        $dangGo = $this->details(['recipient_name' => 'Trần Thị Đang Gõ']);

        $this->delete(self::COUPON, $dangGo)
            ->assertRedirect()
            ->assertSessionHasInput('recipient_name', 'Trần Thị Đang Gõ')
            ->assertSessionHasInput('recipient_phone', '0912345678')
            ->assertSessionHasInput('shipping_address', '12 Đường Thử Nghiệm')
            ->assertSessionHasInput('payment_method', 'cod');
    }

    #[Test]
    public function du_lieu_tra_lai_khong_kem_token_va_method(): void
    {
        // _token và _method là thứ của HTTP, không phải của khách. Để
        // chúng lọt vào old() thì lần dựng biểu mẫu sau có thể dùng nhầm
        // một token đã hết hiệu lực.
        [$user] = $this->shopperWithCart('500000.00');
        $this->claim($user, Coupon::factory()->fixed('50000.00')->create(['code' => 'GIAM50K']));
        $this->get(self::DETAILS);

        $this->delete(self::COUPON, $this->details());

        $this->assertNull(session('_old_input._token'));
        $this->assertNull(session('_old_input._method'));
    }

    #[Test]
    public function chon_giup_toi_bat_lai_viec_tu_chon_ma(): void
    {
        // Đã cho khách tắt thì phải cho bật lại, nếu không họ kẹt với
        // lựa chọn của chính mình cho tới hết phiên.
        [$user] = $this->shopperWithCart('500000.00');
        $this->claim($user, Coupon::factory()->fixed('50000.00')->create(['code' => 'GIAM50K']));

        $this->get(self::DETAILS);
        $this->delete(self::COUPON);
        $this->assertNull($this->appliedCoupon());

        $this->post(self::COUPON.'/tu-chon')->assertRedirect();
        $this->assertSame('GIAM50K', $this->appliedCoupon());
    }

    #[Test]
    public function tu_ap_ma_khac_cung_go_bo_loi_tu_choi(): void
    {
        // Khách quay lại với chuyện mã giảm giá thì lời từ chối trước đó
        // hết hiệu lực — bỏ mã lần nữa mới đặt cờ lên lại.
        [$user] = $this->shopperWithCart('500000.00');
        $this->claim($user, Coupon::factory()->fixed('50000.00')->create(['code' => 'GIAM50K']));
        Coupon::factory()->fixed('30000.00')->create(['code' => 'GIAM30K']);

        $this->get(self::DETAILS);
        $this->delete(self::COUPON);

        $this->post(self::COUPON, ['coupon_code' => 'GIAM30K']);
        $this->assertSame('GIAM30K', $this->appliedCoupon());
        $this->assertFalse(session('checkout.coupon_declined', false));
    }

    #[Test]
    public function ma_khach_tu_chon_khong_bi_he_thong_thay_bang_ma_loi_hon(): void
    {
        // Có thể họ đang giữ mã kia cho đơn sau, hoặc mã kia sắp hết hạn.
        // Hệ thống không biết, và không được đoán.
        [$user] = $this->shopperWithCart('500000.00');
        $this->claim($user, Coupon::factory()->fixed('200000.00')->create(['code' => 'LOINHIEU']));
        $this->claim($user, Coupon::factory()->fixed('10000.00')->create(['code' => 'LOIIT']));

        $this->get(self::DETAILS);
        $this->post(self::COUPON, ['coupon_code' => 'LOIIT']);

        $this->get(self::DETAILS);
        $this->assertSame('LOIIT', $this->appliedCoupon());
    }

    #[Test]
    public function ma_het_han_bi_go_nhung_khong_tat_viec_tu_chon_ma(): void
    {
        // clearCoupon() còn được gọi lúc HỆ THỐNG dọn dẹp. Gộp chuyện đó
        // với "khách từ chối" thì một mã hết hạn cũng tắt luôn tính năng
        // tự chọn mã.
        [$user] = $this->shopperWithCart('500000.00');
        $hetHan = Coupon::factory()->fixed('50000.00')->create(['code' => 'SAPHETHAN']);
        $this->claim($user, $hetHan);
        $this->get(self::DETAILS);
        $this->post(self::COUPON, ['coupon_code' => 'SAPHETHAN']);
        $this->assertSame('SAPHETHAN', $this->appliedCoupon());

        $hetHan->update(['ends_at' => now()->subMinute()]);
        $this->claim($user, Coupon::factory()->fixed('20000.00')->create(['code' => 'CONHIEULUC']));

        $this->get(self::DETAILS);

        $this->assertSame('CONHIEULUC', $this->appliedCoupon(),
            'Mã hết hạn phải rụng và hệ thống vẫn được chọn mã khác.');
    }
}
