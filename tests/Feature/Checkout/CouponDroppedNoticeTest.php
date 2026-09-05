<?php

namespace Tests\Feature\Checkout;

use App\Models\Coupon;
use App\Services\Checkout\CheckoutSource;
use PHPUnit\Framework\Attributes\Test;

/**
 * Mã giảm giá tự rụng thì phải nói cho khách biết.
 * ============================================================
 * Giỏ hàng kiểm lại mã ở MỖI lần dựng, và bỏ mã khi nó không còn dùng
 * được — nhờ vậy con số trên màn hình không bao giờ sai.
 *
 * Nhưng trước bản này, việc bỏ mã diễn ra HOÀN TOÀN IM LẶNG. Khách bớt
 * một món trong giỏ, tổng tiền tăng lên, và không có gì trên trang giải
 * thích vì sao. Người ta sẽ tự tìm lời giải thích, và lời họ tự nghĩ ra
 * thường là "trang web tính sai tiền".
 */
class CouponDroppedNoticeTest extends CheckoutTestCase
{
    #[Test]
    public function bot_hang_lam_ma_rung_thi_co_thong_bao(): void
    {
        /*
         * Cảnh có thật và hay gặp nhất: mã "đơn từ 1 triệu", khách áp
         * được, rồi quay ra bỏ bớt một món.
         */
        [$user, $product] = $this->shopperWithCart('600000.00');

        $this->addToCart($product, 1);   // giỏ thành 1.200.000₫

        $coupon = $this->claimed(
            Coupon::factory()->minOrder('1000000.00')->create(['code' => 'TU1TRIEU']),
            $user,
        );

        $this->post(self::COUPON, ['coupon_code' => $coupon->code]);
        $this->assertSame('TU1TRIEU', $this->appliedCoupon(), 'Mã phải áp được trước đã.');

        // Bỏ bớt: giỏ còn 600.000₫, dưới mức tối thiểu của mã.
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
        /*
         * "Mã không dùng được" thì khách không biết làm gì tiếp. Biết là
         * do chưa đủ tiền hàng thì họ còn một lựa chọn: mua thêm.
         *
         * Lý do lấy thẳng từ CouponService — nơi duy nhất biết vì sao
         * một mã không dùng được.
         */
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
        /*
         * BÀI QUAN TRỌNG NHẤT ở đây, và là lý do không dùng flash().
         *
         * Dữ liệu flash sống qua đúng một request NỮA sau request đặt
         * nó. Mà mã có thể rụng ngay giữa lúc đang dựng trang — khi đó
         * lời nhắn hiện ở trang này RỒI HIỆN LẠI ở trang sau, làm khách
         * tưởng mã vừa rụng thêm lần nữa.
         *
         * Khoá riêng + đọc bằng pull() (đọc xong xoá luôn) thì hiện đúng
         * một lần, bất kể nó được đặt ở request nào.
         */
        [$user, $product] = $this->shopperWithCart('600000.00');
        $this->addToCart($product, 1);

        $this->claimed(
            Coupon::factory()->minOrder('1000000.00')->create(['code' => 'TU1TRIEU']),
            $user,
        );

        $this->post(self::COUPON, ['coupon_code' => 'TU1TRIEU']);
        $this->patch('/gio-hang/'.$this->cartItemId($product), ['quantity' => 1]);

        $this->get(self::DETAILS)->assertSee('đã được gỡ khỏi đơn');

        // Lần tải thứ hai: không được nhắc lại chuyện cũ.
        $this->get(self::DETAILS)
            ->assertOk()
            ->assertDontSee('đã được gỡ khỏi đơn');
    }

    #[Test]
    public function ma_van_dung_duoc_thi_khong_thong_bao_gi(): void
    {
        // Mặt còn lại: một lời nhắn hiện nhầm còn khó chịu hơn không có,
        // vì nó bảo khách rằng ưu đãi của họ vừa mất trong khi vẫn còn.
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
        // Khoá session phải sạch sau khi đọc, nếu không nó sẽ nổi lên ở
        // một trang bất kỳ sau này, tách rời khỏi việc đã gây ra nó.
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
