<?php

namespace App\Services\Checkout;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart\CartService;
use App\Services\Coupon\CouponException;
use App\Services\Coupon\CouponService;
use Illuminate\Support\Collection;

/**
 * Quyết định lần thanh toán này gồm những hàng nào.
 * ============================================================
 * VÌ SAO CÓ LỚP NÀY:
 * Nút "Mua ngay" trước đây thêm sản phẩm vào giỏ rồi chuyển sang trang
 * thanh toán. Hậu quả: khách đang có 3 món trong giỏ, bấm "Mua ngay" ở
 * món thứ 4 thì thanh toán cả 4 — sai hẳn ý nghĩa của nút.
 *
 * Nay "mua ngay" được giữ RIÊNG trong session, không đụng vào giỏ hàng.
 * Giỏ hàng vẫn nguyên vẹn để khách quay lại mua tiếp.
 *
 * Cấu trúc này cũng là nền cho "mua ngay nhiều sản phẩm" sau này: chỉ
 * cần cho phép session giữ nhiều dòng thay vì một.
 */
class CheckoutSource
{
    /** Khoá session giữ món "mua ngay". */
    public const DIRECT_KEY = 'checkout.direct';

    /** Khoá session giữ mã giảm giá khách đã áp. */
    public const COUPON_KEY = 'checkout.coupon';

    /**
     * Mã hiện tại do HỆ THỐNG tự chọn hay do KHÁCH chọn.
     *
     * Phân biệt hai trường hợp là điều kiện để tính năng tự áp mã không
     * trở thành phiền phức: mã tự chọn thì được thay khi giỏ đổi (giỏ to
     * lên có thể mở khoá một mã tốt hơn), còn mã khách tự chọn thì TUYỆT
     * ĐỐI không đụng tới. Ghi đè lựa chọn của người dùng là cách nhanh
     * nhất để họ mất tin vào cả hệ thống.
     */
    public const COUPON_AUTO_KEY = 'checkout.coupon_auto';

    /**
     * Khách đã TỰ TAY bỏ mã cho lần thanh toán này.
     *
     * PHẢI CÓ RIÊNG MỘT KHOÁ, không thể suy ra từ hai khoá trên. Bỏ mã
     * là xoá cả mã lẫn cờ `auto`, nên ngay sau đó session trông y hệt
     * lúc khách chưa có mã nào — và autoApplyBestCoupon() lập tức áp lại
     * đúng cái mã vừa bỏ. Nút "Bỏ mã" khi đó KHÔNG BAO GIỜ hoạt động.
     *
     * Cờ này ghi lại ý định, chứ không chỉ ghi lại trạng thái: "khách
     * không muốn hệ thống chọn mã hộ cho đơn này nữa". Nó chỉ mất khi
     * khách tự áp một mã khác, tự bật lại, hoặc khi đơn đã đặt xong.
     */
    public const COUPON_DECLINED_KEY = 'checkout.coupon_declined';

    /**
     * Lời nhắn MỘT LẦN về việc hệ thống vừa tự gỡ mã giảm giá.
     *
     * VÌ SAO KHÔNG DÙNG flash('info'): dữ liệu flash sống qua ĐÚNG MỘT
     * request nữa sau request đặt nó. Mà mã có thể rụng ngay giữa lúc
     * đang dựng trang — khi đó lời nhắn hiện ở trang này RỒI HIỆN LẠI ở
     * trang sau. Khách đọc hai lần một chuyện đã cũ và tưởng mã vừa rụng
     * thêm lần nữa.
     *
     * Khoá riêng + đọc bằng pull() (đọc xong xoá luôn) thì hiện đúng một
     * lần, bất kể nó được đặt ở request nào.
     */
    public const COUPON_NOTICE_KEY = 'checkout.coupon_notice';

    /**
     * Khoá session giữ dữ liệu biểu mẫu thanh toán đang nhập dở.
     *
     * ĐỊNH NGHĨA Ở ĐÂY, không ở CheckoutController, dù controller mới là
     * nơi ghi vào. Lý do: lớp này cũng phải ĐỌC nó (lấy tỉnh để tính phí
     * giao), và hai lớp cùng gõ tay chuỗi 'checkout.form' là kiểu trùng
     * lặp hỏng âm thầm — đổi ở một bên thì bên kia đọc phải khoá không
     * tồn tại, session().get() trả null, và phí giao lặng lẽ quay về mức
     * mặc định mà không có lỗi nào.
     *
     * Cả ba khoá của luồng thanh toán nay đều nằm cạnh nhau ở đây.
     * LƯU Ý tiền tố: chúng là 'checkout.X' chứ không phải nhánh con của
     * một khoá 'checkout' — session()->forget('checkout') sẽ xoá sạch cả
     * ba, kể cả khoá chống trùng đơn.
     */
    public const FORM_KEY = 'checkout.form';

    public function __construct(
        private readonly CartService $cart,
        private readonly CouponService $coupons,
    ) {
    }

    /**
     * Ghi nhận một lần "mua ngay".
     * Ghi đè lần trước — mỗi lúc chỉ có một phiên mua ngay đang mở.
     */
    public function setDirect(Product $product, int $quantity, ?ProductVariant $variant = null): void
    {
        session([
            self::DIRECT_KEY => [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'quantity' => max(1, $quantity),
            ],
        ]);
    }

    public function clearDirect(): void
    {
        session()->forget(self::DIRECT_KEY);
    }

    public function hasDirect(): bool
    {
        return session()->has(self::DIRECT_KEY);
    }

    /**
     * @param  bool  $auto  true khi hệ thống tự chọn, false khi khách chọn
     */
    public function setCoupon(string $code, bool $auto = false): void
    {
        session([
            self::COUPON_KEY => mb_strtoupper(trim($code)),
            self::COUPON_AUTO_KEY => $auto,
        ]);

        /*
         * Khách tự chọn một mã = họ đã quay lại với chuyện mã giảm giá,
         * nên lời từ chối trước đó hết hiệu lực. Bỏ mã này lần nữa thì
         * declineAutoCoupon() lại đặt cờ lên.
         */
        if (! $auto) {
            session()->forget(self::COUPON_DECLINED_KEY);
        }
    }

    /**
     * Gỡ mã đang áp.
     *
     * KHÔNG đụng tới cờ từ chối: hàm này còn được gọi trong lúc tính
     * lại (mã hết hạn, giỏ tụt xuống dưới mức tối thiểu). Những lần đó
     * là hệ thống dọn dẹp, không phải khách từ chối — gộp hai chuyện
     * lại thì một mã hết hạn cũng tắt luôn tính năng tự chọn mã.
     */
    public function clearCoupon(): void
    {
        session()->forget([self::COUPON_KEY, self::COUPON_AUTO_KEY]);
    }

    /** Khách tự tay bỏ mã: gỡ mã VÀ ngừng tự chọn cho đơn này. */
    public function declineAutoCoupon(): void
    {
        $this->clearCoupon();

        session([self::COUPON_DECLINED_KEY => true]);
    }

    /** Cho phép hệ thống chọn mã trở lại. */
    public function allowAutoCoupon(): void
    {
        session()->forget(self::COUPON_DECLINED_KEY);
    }

    /** Khách có đang từ chối để hệ thống chọn mã không. */
    public function autoCouponDeclined(): bool
    {
        return (bool) session(self::COUPON_DECLINED_KEY, false);
    }

    /** Mã đang áp có phải do hệ thống tự chọn không. */
    public function couponIsAuto(): bool
    {
        return (bool) session(self::COUPON_AUTO_KEY, false);
    }

    /**
     * Tự chọn mã tốt nhất nếu khách CHƯA tự chọn mã nào.
     *
     * Gọi ở đầu trang thanh toán. Ba trường hợp:
     *   - khách đã tự chọn mã  -> không đụng vào;
     *   - đang có mã tự chọn    -> tính lại, vì giỏ có thể đã đổi;
     *   - chưa có mã nào        -> tìm và áp.
     *
     * @return bool có vừa đổi mã hay không (để giao diện báo cho khách)
     */
    public function autoApplyBestCoupon(): bool
    {
        // Khách đã bỏ mã bằng tay thì không áp lại gì nữa cho tới khi
        // họ đổi ý — xem ghi chú ở COUPON_DECLINED_KEY.
        if ($this->autoCouponDeclined()) {
            return false;
        }

        // Khách tự chọn thì thôi — xem ghi chú ở COUPON_AUTO_KEY.
        if (session()->has(self::COUPON_KEY) && ! $this->couponIsAuto()) {
            return false;
        }

        $basket = $this->hasDirect() ? $this->directBasket() : $this->cartBasket();

        if ($basket->isEmpty()) {
            return false;
        }

        $best = app(\App\Services\Coupon\BestCouponFinder::class)
            ->find(\Illuminate\Support\Facades\Auth::user(), $basket->itemsTotal());

        /*
         * MÃ KHÔNG CỘNG DỒN MÀ GIẢM ÍT HƠN ƯU ĐÃI HẠNG: không tự áp.
         *
         * Áp mã đó là tắt ưu đãi hạng — "chọn giúp" mà làm khách thiệt thì
         * ngược hẳn mục đích. Khách vẫn tự áp tay được nếu muốn.
         */
        $hang = $this->hangHienTai();

        if ($best !== null && $hang !== null && ! $best->stack_with_member) {
            $voiHang = $basket->withMemberTier($hang);

            if (bccomp($voiHang->withCoupon($best)->orderDiscountTotal(), $voiHang->orderDiscountTotal(), 2) <= 0) {
                $best = null;
            }
        }

        $current = session(self::COUPON_KEY);

        if ($best === null) {
            /*
             * Không còn mã nào dùng được. Gỡ mã TỰ CHỌN đang có — nó có
             * thể đã hết hạn hoặc giỏ vừa nhỏ đi dưới mức tối thiểu. Để
             * nguyên thì tóm tắt đơn hiện một dòng giảm giá không có
             * thật cho tới lúc khách bấm đặt hàng.
             */
            if ($current !== null) {
                $this->clearCoupon();

                return true;
            }

            return false;
        }

        if ($current === $best->code) {
            return false;
        }

        $this->setCoupon($best->code, auto: true);

        return true;
    }

    /**
     * Giỏ hàng để thanh toán: ưu tiên món "mua ngay" nếu đang có,
     * kèm mã giảm giá nếu mã còn dùng được.
     */
    public function basket(): CheckoutBasket
    {
        $basket = $this->hasDirect()
            ? $this->directBasket()
            : $this->cartBasket();

        /*
         * Gắn nơi nhận hàng vào giỏ ngay tại đây.
         *
         * Phí giao nay phụ thuộc tỉnh, nên MỌI nơi đọc giỏ hàng đều cần
         * thông tin đó: màn hình thanh toán, tóm tắt tiền trong Blade, và
         * OrderService lúc ghi đơn. Nếu để từng nơi tự lấy tỉnh từ session
         * rồi tự gọi withProvince(), chỉ cần một nơi quên là con số khách
         * nhìn thấy và con số ghi vào đơn lệch nhau — mà lệch về tiền thì
         * không có cách nào chữa sau khi đơn đã đặt.
         *
         * Tỉnh lấy từ session đã qua kiểm tra hợp lệ ở bước nhập địa chỉ
         * (Provinces::isValid trong CheckoutRequest), không phải chuỗi
         * thô từ request.
         */
        $province = session(self::FORM_KEY)['shipping_province'] ?? null;

        /*
         * Mã địa giới GHN đi kèm tỉnh, và đi cùng một đường.
         *
         * Cùng lý do với việc gắn tỉnh ở trên: mọi nơi đọc giỏ hàng đều
         * cần chúng để ra đúng con số cước. Để từng nơi tự lấy từ session
         * thì chỉ cần một nơi quên là con số khách nhìn thấy và con số
         * ghi vào đơn lệch nhau — mà lệch về tiền thì không chữa được
         * sau khi đơn đã đặt.
         *
         * Giá trị đã qua kiểm tra ở bước nhập địa chỉ (CheckoutDetailsRequest),
         * không phải chuỗi thô từ request.
         */
        $form = session(self::FORM_KEY) ?? [];

        $coMa = $basket
            ->withProvince(is_string($province) ? $province : null)
            ->withGhnDestination(
                isset($form['to_district_id']) ? (int) $form['to_district_id'] : null,
                isset($form['to_ward_code']) ? (string) $form['to_ward_code'] : null,
            )
            // Hạng gắn TRƯỚC mã: mã tính trên tiền hàng đã trừ ưu đãi hạng.
            ->withMemberTier($this->hangHienTai())
            ->withCoupon($this->resolveCoupon($basket));

        // Điểm gắn SAU mã: trần điểm tính trên tiền hàng đã trừ mã.
        return $coMa->withPoints($this->resolvePoints($coMa));
    }

    /** Hạng thành viên của người đang thanh toán; khách vãng lai không có hạng. */
    private function hangHienTai(): ?\App\Models\MemberTier
    {
        $user = \Illuminate\Support\Facades\Auth::user();

        return $user ? app(\App\Services\Loyalty\MemberTierResolver::class)->cua($user)['hang'] : null;
    }

    /** Khoá session giữ số điểm khách muốn dùng cho lần thanh toán này. */
    public const POINTS_KEY = 'checkout.points';

    public function setPoints(int $points): void
    {
        session([self::POINTS_KEY => max(0, $points)]);
    }

    public function clearPoints(): void
    {
        session()->forget(self::POINTS_KEY);
    }

    public function requestedPoints(): int
    {
        return (int) session(self::POINTS_KEY, 0);
    }

    /**
     * Số điểm được dùng: kẹp theo SỐ DƯ THẬT lúc này.
     *
     * Đọc lại số dư mỗi lần dựng giỏ: khách mở hai tab, dùng điểm ở tab
     * kia, thì tab này phải tự hạ xuống — không để tóm tắt đơn hứa một
     * khoản giảm mà lúc đặt hàng bị từ chối. Lúc đặt hàng OrderService
     * vẫn khoá và kiểm lại lần nữa.
     */
    private function resolvePoints(CheckoutBasket $basket): int
    {
        $muon = $this->requestedPoints();
        $user = \Illuminate\Support\Facades\Auth::user();

        if ($muon <= 0 || $user === null || $basket->isEmpty()) {
            return 0;
        }

        return \App\Services\Points\PointRedemption::dungDuoc(
            $muon,
            $basket->itemsAfterCoupon(),
            app(\App\Services\Points\PointLedger::class)->soDu($user),
        );
    }

    /**
     * Mã đã áp còn hợp lệ với giỏ hàng HIỆN TẠI không.
     *
     * Phải kiểm tra lại mỗi lần chứ không tin lần áp trước: khách áp mã
     * "đơn từ 1 triệu" rồi quay ra xoá bớt hàng, mã phải tự rụng chứ
     * không được giảm tiếp. Mã hỏng thì xoá khỏi session luôn để con số
     * hiển thị không bao giờ sai.
     */
    private function resolveCoupon(CheckoutBasket $basket): ?\App\Models\Coupon
    {
        $code = session(self::COUPON_KEY);

        if (! $code || $basket->isEmpty()) {
            return null;
        }

        try {
            return $this->coupons->resolve($code, $basket->itemsTotal());
        } catch (CouponException $e) {
            $this->clearCoupon();

            /*
             * NÓI CHO KHÁCH BIẾT MÃ VỪA RỤNG, VÀ VÌ SAO.
             *
             * Con số trên màn hình vẫn luôn đúng — chỗ này gỡ mã trước
             * khi tính tiền, nên khách không bao giờ bị tính sai. Nhưng
             * đúng số mà không có lời giải thích thì vẫn là một trải
             * nghiệm tồi: khách bớt một món trong giỏ, tổng tiền tăng
             * lên, và không có gì trên trang nói vì sao.
             *
             * Người ta sẽ tự tìm lời giải thích, và lời họ tự nghĩ ra
             * thường là "trang web tính sai tiền".
             *
             * Kèm luôn LÝ DO từ CouponService — nó đã nói rõ "đơn chưa
             * đủ 1.000.000₫" hay "mã đã hết hạn". Chỉ báo "mã không dùng
             * được" thì khách không biết phải làm gì tiếp; biết là do
             * chưa đủ tiền hàng thì họ còn có lựa chọn mua thêm.
             */
            session([self::COUPON_NOTICE_KEY => sprintf(
                'Mã giảm giá %s đã được gỡ khỏi đơn: %s',
                $code,
                lcfirst($e->getMessage()),
            )]);

            return null;
        }
    }

    public function cartBasket(): CheckoutBasket
    {
        $gio = $this->cart->current();

        /*
         * NẠP SẴN NHÓM THUẾ CÙNG SẢN PHẨM.
         *
         * Thuế nay tính theo TỪNG DÒNG, nên mỗi dòng phải biết nhóm thuế
         * của sản phẩm mình. Không nạp sẵn thì mỗi món trong giỏ là một
         * câu truy vấn nữa — và màn hình thanh toán gọi tới nó vài lần.
         */
        $gio->items->loadMissing('product.taxClass');

        $lines = $gio->items
            /*
             * CHỈ LẤY MÓN ĐÃ CHỌN.
             *
             * Khách để dành vài món chờ lương và muốn mua trước một bó
             * hoa sinh nhật — giỏ giữ nguyên, đơn chỉ gồm thứ họ tích.
             *
             * Lọc Ở ĐÂY chứ không ở controller: mọi nơi tính tiền đều đi
             * qua hàm này (trang giỏ, trang thanh toán, lúc ghi đơn), nên
             * không có chỗ nào tính nhầm trên cả giỏ.
             */
            ->filter(fn ($item) => $item->is_selected !== false)
            ->filter(fn ($item) => $item->product !== null)
            ->map(fn ($item) => new CheckoutLine(
                $item->product,
                $item->variant,
                (int) $item->quantity,
            ))
            ->values();

        return new CheckoutBasket($lines, 'cart');
    }

    private function directBasket(): CheckoutBasket
    {
        $data = session(self::DIRECT_KEY);

        $product = Product::with(['promotions', 'category', 'taxClass'])->find($data['product_id'] ?? null);

        /*
         * Sản phẩm bị xoá hoặc ngừng bán trong lúc khách đang thanh
         * toán: bỏ phiên mua ngay và trả về giỏ rỗng để controller đưa
         * khách về giỏ hàng kèm thông báo, thay vì lỗi 500.
         */
        if (! $product || $product->status !== 'active') {
            $this->clearDirect();

            return new CheckoutBasket(collect(), 'direct');
        }

        $variant = $data['variant_id']
            ? ProductVariant::where('product_id', $product->id)->find($data['variant_id'])
            : null;

        $line = new CheckoutLine($product, $variant, (int) ($data['quantity'] ?? 1));

        return new CheckoutBasket(new Collection([$line]), 'direct');
    }
}
