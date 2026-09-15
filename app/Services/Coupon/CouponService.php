<?php

namespace App\Services\Coupon;

use App\Enums\CouponType;
use App\Enums\PaymentMethod;
use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Kiểm tra và tính mã giảm giá.
 * ============================================================
 * NƠI DUY NHẤT quyết định một mã có dùng được không và giảm bao nhiêu.
 * Trình duyệt không bao giờ gửi lên số tiền giảm — nó chỉ gửi CHUỖI MÃ.
 *
 * Quan hệ với các lớp tính tiền khác:
 *   - PricingService : giá của TỪNG SẢN PHẨM (khuyến mại theo sản phẩm)
 *   - CouponService  : giảm trên TỔNG TIỀN HÀNG của đơn
 *   - CheckoutBasket : cộng tất cả lại thành số tiền cuối cùng
 *
 * Hai loại giảm giá này ĐỘC LẬP và có thể cùng áp trên một đơn: khuyến
 * mại làm giảm giá từng món trước, mã giảm giá áp lên tổng sau đó.
 */
class CouponService
{
    public function __construct(
        private readonly CouponWallet $wallet,
    ) {
    }

    /**
     * Tìm mã và kiểm tra đủ điều kiện.
     *
     * @param  string  $itemsTotal  tổng tiền hàng sau khuyến mại sản phẩm
     *
     * @throws CouponException với thông điệp nói rõ vì sao không dùng được
     */
    public function resolve(string $code, string $itemsTotal, ?PaymentMethod $paymentMethod = null): Coupon
    {
        $code = mb_strtoupper(trim($code));

        $coupon = Coupon::where('code', $code)->first();

        if (! $coupon) {
            throw new CouponException('Mã giảm giá không tồn tại.');
        }

        $this->check($coupon, $itemsTotal, $paymentMethod);

        return $coupon;
    }

    /**
     * Mã ĐÃ CÓ TRONG TAY có dùng được cho đơn này không.
     *
     * TÁCH KHỎI resolve() để danh sách "chọn mã giảm giá" ở trang thanh
     * toán hỏi được cùng một câu bằng cùng một luật. Nếu chỗ đó tự viết
     * lại mấy phép so sánh (còn hạn? đủ mức tối thiểu?) thì sớm muộn hai
     * bản sẽ lệch — và lệch kiểu này nghĩa là danh sách bảo "dùng được"
     * còn nút bấm trả về lỗi, hoặc tệ hơn, ngược lại.
     *
     * @throws CouponException với thông điệp nói rõ vì sao không dùng được
     */
    public function check(Coupon $coupon, string $itemsTotal, ?PaymentMethod $paymentMethod = null): void
    {
        if (! $coupon->isRunning()) {
            // Không nói rõ "hết hạn" hay "chưa bắt đầu" hay "đang tạm dừng"
            // — chi tiết đó là chuyện nội bộ của cửa hàng.
            throw new CouponException('Mã giảm giá không còn hiệu lực.');
        }

        if ($coupon->isExhausted()) {
            throw new CouponException('Mã giảm giá đã hết lượt sử dụng.');
        }

        /*
         * MÃ CÓ CHỦ — voucher đổi từ điểm thưởng.
         *
         * Người khác (kể cả khách vãng lai) biết mã cũng không dùng được.
         * Báo "không tồn tại" y như mã gõ bừa: câu "mã này của người khác"
         * xác nhận cho người dò mã rằng họ vừa đoán trúng.
         */
        if ($coupon->owner_user_id !== null && (int) $coupon->owner_user_id !== Auth::id()) {
            throw new CouponException('Mã giảm giá không tồn tại.');
        }

        /*
         * Giới hạn RIÊNG của từng tài khoản (coupons.per_user_limit).
         *
         * Khác hẳn isExhausted() ở trên: cái kia hỏi "chương trình còn
         * lượt không", cái này hỏi "người này còn suất không".
         *
         * Lấy người dùng từ Auth chứ không nhận qua tham số, vì lớp này
         * được gọi từ ba chỗ (áp mã, tính lại giỏ, ghi đơn) và bắt cả ba
         * cùng truyền đúng một đối tượng là ba cơ hội để quên. Khách vãng
         * lai trả về null và giới hạn này không áp — đó là giới hạn thật
         * của việc cho đặt hàng không cần đăng nhập, xem CouponWallet.
         */
        if ($this->wallet->userLimitReached(Auth::user(), $coupon)) {
            throw new CouponException('Bạn đã dùng hết số lần cho phép của mã này.');
        }

        /*
         * MÃ DÀNH CHO HẠNG THÀNH VIÊN ("ưu tiên voucher" theo hạng).
         *
         * So theo NGƯỠNG của hạng, không theo id: cửa hàng sửa ngưỡng thì
         * thứ bậc vẫn đúng. Khách vãng lai không có hạng — không dùng được.
         * Kiểm ở đây nên nhập tay, chọn trong ví, tự chọn mã đều đi qua.
         */
        if ($coupon->min_member_tier_id !== null) {
            $can = \App\Models\MemberTier::find($coupon->min_member_tier_id);
            $user = Auth::user();
            $hang = $user ? app(\App\Services\Loyalty\MemberTierResolver::class)->cua($user)['hang'] : null;

            if ($can !== null && ($hang === null || bccomp((string) $hang->min_spend, (string) $can->min_spend, 2) < 0)) {
                throw new CouponException('Mã này dành cho thành viên hạng ' . $can->name . ' trở lên.');
            }
        }

        /*
         * GIỚI HẠN HÌNH THỨC THANH TOÁN.
         *
         * Chỉ kiểm khi NƠI GỌI BIẾT hình thức thanh toán. Lúc khách bấm
         * "Áp dụng mã" ở bước 2 thì họ chưa chắc đã chọn xong hình thức,
         * nên chặn ở đó là chặn oan. Nơi bắt buộc phải kiểm là lúc GHI
         * ĐƠN — xem CheckoutController::place().
         *
         * Điều kiện ghi trên giấy mà không ai chặn thì tệ hơn không ghi:
         * khách đọc "chỉ áp dụng khi chuyển khoản", chọn COD, và vẫn được
         * giảm — lần sau họ không tin bất cứ điều kiện nào nữa.
         */
        if ($paymentMethod !== null && ! $coupon->acceptsPayment($paymentMethod)) {
            $labels = array_map(
                fn (PaymentMethod $m) => $m->label(),
                $coupon->allowedPaymentMethods(),
            );

            /*
             * $labels RỖNG khi mã bị giới hạn vào một hình thức KHÔNG
             * CÒN TỒN TẠI (ví dụ mã cũ chỉ dành cho chuyển khoản). Câu
             * "chỉ áp dụng khi thanh toán bằng: ." thì khách không hiểu
             * gì, mà admin cũng không biết phải sửa ở đâu.
             */
            throw new CouponException($labels === []
                ? sprintf(
                    'Mã %s bị giới hạn ở một hình thức thanh toán không còn được hỗ trợ. '
                        . 'Vui lòng dùng mã khác.',
                    $coupon->code,
                )
                : sprintf(
                    'Mã %s chỉ áp dụng khi thanh toán bằng: %s.',
                    $coupon->code,
                    implode(', ', $labels),
                ));
        }

        if ($coupon->min_order_amount && bccomp($itemsTotal, (string) $coupon->min_order_amount, 2) < 0) {
            throw new CouponException(sprintf(
                'Mã này chỉ áp dụng cho đơn từ %sđ.',
                number_format((float) $coupon->min_order_amount, 0, ',', '.'),
            ));
        }
    }

    /**
     * Như check() nhưng trả lời bằng chuỗi lý do thay vì ném ngoại lệ.
     *
     * Dùng khi cần DỰNG DANH SÁCH: ở đó "không dùng được" là một trạng
     * thái bình thường phải hiển thị, không phải một sự cố.
     *
     * @return string|null null nghĩa là dùng được
     */
    public function reasonUnusable(Coupon $coupon, string $itemsTotal): ?string
    {
        try {
            $this->check($coupon, $itemsTotal);

            return null;
        } catch (CouponException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Số tiền được giảm, đã kẹp trong khoảng hợp lệ.
     *
     * @param  string  $itemsTotal  tổng tiền hàng sau khuyến mại sản phẩm
     */
    public function discountFor(Coupon $coupon, string $itemsTotal): string
    {
        $discount = match ($coupon->type) {
            CouponType::Percent => bcdiv(
                bcmul($itemsTotal, (string) $coupon->value, 4),
                '100',
                2,
            ),
            CouponType::FixedAmount => bcadd((string) $coupon->value, '0', 2),
        };

        // Trần giảm — chỉ có ý nghĩa với kiểu phần trăm.
        if ($coupon->max_discount_amount
            && bccomp($discount, (string) $coupon->max_discount_amount, 2) > 0) {
            $discount = bcadd((string) $coupon->max_discount_amount, '0', 2);
        }

        /*
         * Không bao giờ giảm quá tiền hàng. Thiếu chốt chặn này thì một
         * mã "giảm 500.000đ" áp lên đơn 200.000đ sẽ ra tổng ÂM.
         */
        if (bccomp($discount, $itemsTotal, 2) > 0) {
            $discount = $itemsTotal;
        }

        return bccomp($discount, '0', 2) < 0 ? '0.00' : $discount;
    }

    /**
     * Ghi nhận một lượt dùng, gọi SAU khi đơn đã tạo thành công.
     *
     * Tăng bằng câu lệnh increment của cơ sở dữ liệu chứ không đọc rồi
     * cộng rồi ghi: hai khách đặt cùng lúc mà đọc-cộng-ghi thì cả hai
     * cùng đọc thấy used_count = 9 và cùng ghi 10, mã bị dùng lố.
     */
    public function redeem(Coupon $coupon, Order $order): void
    {
        DB::transaction(function () use ($coupon, $order) {
            /*
             * TĂNG BỘ ĐẾM KÈM ĐIỀU KIỆN, rồi kiểm SỐ DÒNG BỊ ẢNH HƯỞNG.
             *
             * LỖI TRƯỚC KHI SỬA: câu cũ là increment('used_count') trần.
             * increment() chống được "mất bản ghi đè" (lost update) —
             * hai request cùng +1 thì ra +2, không phải +1. Nhưng nó
             * KHÔNG chặn vượt giới hạn, và chú thích cũ nhầm hai chuyện
             * đó làm một.
             *
             * Mã còn 1 lượt (used_count = 9, usage_limit = 10):
             *
             *   A kiểm resolve() -> còn 1, cho qua
             *   B kiểm resolve() -> còn 1, cho qua
             *   A increment -> 10
             *   B increment -> 11        <- vượt giới hạn
             *
             * Cả hai đều qua vì phép kiểm và phép ghi là hai bước rời
             * nhau. Gộp lại thành MỘT câu UPDATE có điều kiện thì cơ sở
             * dữ liệu tự phân xử: chỉ một trong hai câu tìm thấy dòng
             * thoả `used_count < usage_limit`, câu còn lại đổi 0 dòng.
             *
             * usage_limit IS NULL nghĩa là không giới hạn — luôn qua.
             */
            $ghiNhan = Coupon::whereKey($coupon->id)
                ->where(fn ($q) => $q
                    ->whereNull('usage_limit')
                    ->orWhereColumn('used_count', '<', 'usage_limit'))
                ->update(['used_count' => DB::raw('used_count + 1')]);

            if ($ghiNhan === 0) {
                /*
                 * Người khác vừa lấy mất lượt cuối trong lúc khách này
                 * đang điền thông tin. Ném ngoại lệ để transaction tạo
                 * đơn cuộn lại — thà không có đơn còn hơn có một đơn
                 * được giảm giá bằng lượt không tồn tại.
                 */
                throw new CouponException(
                    'Mã '.$coupon->code.' vừa hết lượt sử dụng. '
                    .'Vui lòng bỏ mã và đặt lại đơn.'
                );
            }

            /*
             * Đếm riêng cho tài khoản đặt đơn.
             *
             * Đặt ở ĐÂY chứ không ở controller: mọi đường tạo đơn đều đi
             * qua hàm này, nên không có lối nào tăng bộ đếm chung mà quên
             * bộ đếm riêng. Hai con số lệch nhau thì per_user_limit chặn
             * sai người.
             *
             * markUsed dùng upsert nên khách NHẬP TAY một mã chưa từng
             * lưu vào ví vẫn được đếm — nếu chỉ đếm mã đã lưu thì giới
             * hạn thành vô nghĩa với đúng những người không dùng nút Lưu.
             */
            $this->wallet->markUsed($order->user, $coupon);
        });
    }

    /**
     * Trả lại lượt dùng khi đơn bị huỷ.
     *
     * VÌ SAO CẦN: mã có usage_limit = 50 mà 20 đơn bị huỷ thì chỉ còn 30
     * khách thật được dùng, dù 20 đơn kia chưa bao giờ thành đơn hàng.
     * Từ khi khách tự huỷ được, đây còn là đường để một người đốt sạch
     * lượt của mã: đặt rồi huỷ, đặt rồi huỷ.
     *
     * Dùng câu lệnh của cơ sở dữ liệu, và có sàn 0 ngay trong câu lệnh —
     * đọc rồi trừ rồi ghi sẽ sai khi hai đơn cùng huỷ một lúc.
     */
    public function release(Order $order): void
    {
        if (! $order->coupon_id) {
            return;
        }

        Coupon::whereKey($order->coupon_id)
            ->where('used_count', '>', 0)
            ->decrement('used_count');

        // Trả lại cả suất riêng của tài khoản, nếu không thì khách huỷ
        // đơn xong mất luôn lượt dùng mã của mình.
        $coupon = Coupon::find($order->coupon_id);

        if ($coupon) {
            $this->wallet->releaseUse($order->user, $coupon);
        }
    }
}
