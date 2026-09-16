<?php

namespace Tests\Feature\Checkout;

use App\Models\Coupon;
use App\Services\Checkout\CheckoutSource;
use PHPUnit\Framework\Attributes\Test;

/** Mã giảm giá tự rụng thì phải nói cho khách biết. */
class CouponDroppedNoticeTest extends CheckoutTestCase
{
    #[Test]
    public function bot_hang_lam_ma_rung_thi_co_thong_bao(): void
    {
        [$user, $product] = $this->shopperWithCart('600000.00');

        $this->addToCart($product, 1);

        $coupon = $this->claimed(
            Coupon::factory()->minOrder('1000000.00')->create(['code' => 'TU1TRIEU']),
            $user,
        );

        $this->post(self::COUPON, ['coupon_code' => $coupon->code]);
        $this->assertSame('TU1TRIEU', $this->appliedCoupon(), 'Mã phải áp được trước đã.');

        $this->patch('/gio-hang/'.$this->cartItemId($product), ['quantity' => 1]);

        $this->get(self::DETAILS)
            ->assertOk()
            ->assertSee('TU1TRIEU')
            ->assertSee('đã được gỡ khỏi đơn');

        $this->assertNull($this->appliedCoupon(), 'Mã phải bị gỡ khỏi phiên.');
    }

    #[Test]
    public function thong_bao_noi_ro_LY_DO(): void
    {
        [$user, $product] = $this->shopperWithCart('600000.00');
        $this->addToCart($product, 1);

        $this->claimed(
            Coupon::factory()->minOrder('1000000.00')->create(['code' => 'TU1TRIEU']),
            $user,
        );

        $this->post(self::COUPON, ['coupon_code' => 'TU1TRIEU']);
        $this->patch('/gio-hang/'.$this->cartItemId($product), ['quantity' => 1]);

        $html = $this->get(self::DETAILS)->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/1\.000\.000/u',
            $html,
            'Thông báo phải nói rõ mức tối thiểu chưa đạt, không chỉ nói "không dùng được".',
        );
    }

    #[Test]
    public function thong_bao_chi_hien_DUNG_MOT_LAN(): void
    {
        [$user, $product] = $this->shopperWithCart('600000.00');
        $this->addToCart($product, 1);

        $this->claimed(
            Coupon::factory()->minOrder('1000000.00')->create(['code' => 'TU1TRIEU']),
            $user,
        );

        $this->post(self::COUPON, ['coupon_code' => 'TU1TRIEU']);
        $this->patch('/gio-hang/'.$this->cartItemId($product), ['quantity' => 1]);

        $this->get(self::DETAILS)->assertSee('đã được gỡ khỏi đơn');

        $this->get(self::DETAILS)
            ->assertOk()
            ->assertDontSee('đã được gỡ khỏi đơn');
    }

    #[Test]
    public function ma_van_dung_duoc_thi_khong_thong_bao_gi(): void
    {
        [$user, $product] = $this->shopperWithCart('600000.00');
        $this->addToCart($product, 1);

        $this->claimed(
            Coupon::factory()->minOrder('1000000.00')->create(['code' => 'TU1TRIEU']),
            $user,
        );

        $this->post(self::COUPON, ['coupon_code' => 'TU1TRIEU']);

        $this->get(self::DETAILS)
            ->assertOk()
            ->assertDontSee('đã được gỡ khỏi đơn');

        $this->assertSame('TU1TRIEU', $this->appliedCoupon());
    }

    #[Test]
    public function khong_con_du_lieu_thong_bao_sot_lai_trong_phien(): void
    {
        [$user, $product] = $this->shopperWithCart('600000.00');
        $this->addToCart($product, 1);

        $this->claimed(
            Coupon::factory()->minOrder('1000000.00')->create(['code' => 'TU1TRIEU']),
            $user,
        );

        $this->post(self::COUPON, ['coupon_code' => 'TU1TRIEU']);
        $this->patch('/gio-hang/'.$this->cartItemId($product), ['quantity' => 1]);
        $this->get(self::DETAILS);

        $this->assertFalse(
            session()->has(CheckoutSource::COUPON_NOTICE_KEY),
            'Đọc xong phải xoá, không để sót lại trong phiên.',
        );
    }
}
