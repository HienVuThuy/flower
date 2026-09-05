<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\Coupon\CouponWallet;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Trang sự kiện — landing page riêng cho một chương trình khuyến mại.
 * ============================================================
 * VÌ SAO KHÔNG CHỈ LÀ /san-pham?promotion=slug:
 * Bản cũ, nút "Xem ưu đãi" trên thanh thông báo dẫn tới trang danh sách
 * sản phẩm đã lọc — đúng hàng, nhưng KHÔNG CÓ GÌ CỦA SỰ KIỆN. Khách bấm
 * vào một banner Giáng sinh và rơi vào một trang lưới sản phẩm bình
 * thường, không biết chương trình này là gì, giảm bao nhiêu, tới bao giờ,
 * hay có mã nào để lấy.
 *
 * Trang này gom ĐỦ BA THỨ một sự kiện cần có:
 *   1. bối cảnh  — tên, mô tả, banner, thời gian, mức giảm;
 *   2. hàng      — toàn bộ sản phẩm trong chương trình;
 *   3. mã        — voucher riêng của sự kiện, lưu ngay tại đây.
 *
 * MÀU SẮC THEO THEME CỦA CHƯƠNG TRÌNH (cột `theme_key`) nên trang Giáng
 * sinh trông khác trang Tết — đó là điểm của một landing page.
 *
 * XEM ĐƯỢC CẢ KHI CHƯƠNG TRÌNH ĐÃ KẾT THÚC, và cố ý như vậy: đường dẫn
 * được chia sẻ trên mạng xã hội, chết link là mất khách. Trang tự nói rõ
 * chương trình đã hết và không cho lưu mã nữa.
 */
class EventController extends Controller
{
    public function __construct(
        private readonly CouponWallet $wallet,
    ) {
    }

    public function show(Promotion $promotion): View
    {
        /*
         * Chương trình còn NHÁP thì không ai ngoài admin được xem — nó
         * chưa được duyệt, giá và nội dung có thể còn sai.
         */
        abort_if($promotion->status === \App\Enums\PromotionStatus::Draft, 404);

        $products = Product::query()
            ->with(['category', 'promotions',
                // Thẻ sản phẩm phải biết hàng này có quy cách hay
                // không để hiện đúng nút; hỏi từng thẻ là N+1.
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->whereHas('promotions', fn ($q) => $q->where('promotions.id', $promotion->id))
            ->whereIn('status', ['active', 'out_of_stock'])
            ->orderByEffectivePrice('asc')
            ->get();

        /*
         * Mã RIÊNG của sự kiện này.
         *
         * Chỉ mã công khai — mã nội bộ vẫn nhập tay được nhưng không hiện
         * cho tất cả mọi người (xem QĐ-21).
         */
        $coupons = Coupon::query()
            ->where('promotion_id', $promotion->id)
            ->where('is_public', true)
            ->orderByDesc('value')
            ->get();

        $user = Auth::user();

        return view('shop.events.show', [
            'promotion' => $promotion,
            'products' => $products,
            'coupons' => $coupons,

            /*
             * Mã nào khách đã lưu rồi — để thẻ voucher hiện "Đã lưu" thay
             * vì mời lưu lại lần nữa.
             */
            'claimedIds' => $user
                ? $this->wallet->forUser($user)->pluck('coupon.id')->all()
                : [],

            'isRunning' => $promotion->isRunning(),
        ]);
    }
}
