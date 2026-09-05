<?php

namespace App\Services\Coupon;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Tự chọn mã giảm giá TỐT NHẤT trong VÍ của khách.
 * ============================================================
 * VÌ SAO CẦN:
 * Khách lưu năm cái mã vào ví rồi tới bước thanh toán phải tự đoán cái
 * nào lợi nhất — mà muốn biết thì phải thử từng cái, xem tổng tiền đổi
 * bao nhiêu, rồi bỏ ra thử cái khác. Không ai làm thế. Kết quả là mã nằm
 * trong ví không được dùng, và khách trả nhiều hơn mức cửa hàng sẵn sàng
 * giảm — thiệt cho cả hai bên.
 *
 * BA RÀNG BUỘC, và đây là phần quan trọng hơn cả thuật toán:
 *
 *   1. CHỈ XÉT MÃ ĐÃ LƯU VÀO VÍ.
 *      Bản trước còn tự áp cả mã công khai đang chạy dù khách chưa lưu.
 *      Nghe thì hào phóng, nhưng nó phá đúng lời hứa in trên trang
 *      Voucher: "Lưu mã về ví, tới bước thanh toán chọn lại là xong."
 *      Mã chưa lưu mà vẫn tự áp thì nút "Lưu mã" và cả khái niệm ví
 *      không còn nghĩa gì — khách thấy "Ví voucher đang trống" mà vẫn
 *      được giảm, và không hiểu tiền ở đâu ra.
 *
 *      Mã công khai KHÔNG biến mất: khách vẫn lưu được bằng một cú bấm ở
 *      trang Voucher, và vẫn NHẬP TAY được ở bước thanh toán. Chỉ có
 *      việc tự động áp là đòi hỏi khách phải nhận mã trước.
 *
 *   2. KHÔNG BAO GIỜ GHI ĐÈ LỰA CHỌN CỦA KHÁCH.
 *      Khách tự nhập hay tự chọn một mã thì đó là quyết định của họ, kể
 *      cả khi mã đó giảm ít hơn. Có thể họ đang giữ mã kia cho đơn sau,
 *      hoặc mã kia sắp hết hạn. Hệ thống không biết, và không được đoán.
 *      Cờ `auto` trong session phân biệt hai trường hợp.
 *
 *   3. TÍNH BẰNG CHÍNH CouponService, không tự tính lại.
 *      Mọi điều kiện (còn hạn, còn lượt, đủ giá trị đơn, giới hạn mỗi
 *      tài khoản) đã nằm ở đó. Viết lại phép tính ở đây là tạo ra bản sao
 *      thứ hai của luật tính tiền — thứ chắc chắn sẽ lệch.
 */
class BestCouponFinder
{
    public function __construct(
        private readonly CouponService $coupons,
    ) {
    }

    /**
     * Mã cho số tiền giảm lớn nhất, hoặc null nếu không mã nào dùng được.
     *
     * @param  string  $itemsTotal  tổng tiền hàng sau khuyến mại sản phẩm
     */
    public function find(?User $user, string $itemsTotal): ?Coupon
    {
        $best = null;
        $bestDiscount = '0.00';

        foreach ($this->candidates($user) as $coupon) {
            try {
                /*
                 * resolve() ném CouponException khi mã không dùng được —
                 * hết hạn, hết lượt, chưa đủ giá trị đơn, hoặc khách đã
                 * dùng hết suất. Bắt và bỏ qua là đúng: ở đây ta đang
                 * DUYỆT ứng viên, không phải phản hồi một thao tác của
                 * khách, nên không có gì để báo lỗi.
                 */
                $this->coupons->resolve($coupon->code, $itemsTotal);
            } catch (CouponException) {
                continue;
            }

            $discount = $this->coupons->discountFor($coupon, $itemsTotal);

            /*
             * Hoà thì GIỮ CÁI ĐANG CÓ — dùng `>` chứ không phải `>=`.
             *
             * Hai mã giảm bằng nhau thì đổi qua đổi lại giữa hai lần tải
             * trang là vô nghĩa với khách mà lại làm con số nhấp nháy.
             */
            if (bccomp($discount, $bestDiscount, 2) > 0) {
                $best = $coupon;
                $bestDiscount = $discount;
            }
        }

        return $best;
    }

    /**
     * Mã trong ví của khách, còn trong thời gian chạy.
     *
     * KHÁCH VÃNG LAI TRẢ VỀ RỖNG. Không có tài khoản thì không có ví, nên
     * không có gì để tự chọn — họ vẫn nhập tay được mã ở bước thanh toán.
     * Đó là giới hạn thật của việc cho đặt hàng không cần đăng nhập,
     * không phải chỗ để bù bằng cách phát mã cho tất cả.
     *
     * @return \Illuminate\Support\Collection<int, Coupon>
     */
    private function candidates(?User $user): \Illuminate\Support\Collection
    {
        if ($user === null) {
            return collect();
        }

        $walletIds = DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->pluck('coupon_id');

        if ($walletIds->isEmpty()) {
            return collect();
        }

        return Coupon::query()
            ->whereIn('id', $walletIds)
            ->usableNow()
            ->get();
    }
}
