<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\ReviewRequest;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * Khách viết và gỡ đánh giá sản phẩm.
 *
 * Quy tắc "ai được đánh giá" KHÔNG viết ở đây — nó nằm trong
 * Review::pendingOrderFor(). Blade dùng chính hàm đó để quyết định có
 * hiện form hay không, nên nút bấm và quyền thật không bao giờ lệch nhau.
 */
class ReviewController extends Controller
{
    public function store(ReviewRequest $request, Product $product): RedirectResponse
    {
        $userId = Auth::id();

        /*
         * Kiểm tra lại ở đây dù Blade đã ẩn form.
         *
         * Giao diện chỉ là gợi ý; request có thể được gửi thẳng bằng công
         * cụ khác. Đây mới là chỗ quyết định.
         */
        $order = Review::pendingOrderFor($userId, $product->id);

        if (! $order) {
            return back()->with('error',
                'Bạn chỉ đánh giá được sản phẩm đã mua và đã nhận hàng. '
                . 'Mỗi đơn hàng viết được một đánh giá.');
        }

        Review::create([
            'product_id' => $product->id,
            'user_id' => $userId,
            // Lưu đơn làm bằng chứng mua hàng — xem chú thích ở migration.
            'order_id' => $order->id,
            'rating' => $request->validated('rating'),
            'comment' => $request->validated('comment'),
        ]);

        return back()->with('success', 'Cảm ơn bạn đã đánh giá sản phẩm.');
    }

    /**
     * Khách gỡ đánh giá của CHÍNH MÌNH.
     *
     * Xoá hẳn chứ không ẩn: đây là nội dung của khách, họ rút lại thì
     * không có lý do gì cửa hàng còn giữ. (Khác với cửa hàng gỡ bài — lúc
     * đó dùng is_visible để còn dấu vết xử lý.)
     */
    public function destroy(Review $review): RedirectResponse
    {
        abort_unless($review->user_id === Auth::id(), 403);

        $review->delete();

        return back()->with('success', 'Đã gỡ đánh giá của bạn.');
    }

    /**
     * Danh sách đánh giá của chính mình.
     *
     * VẤN ĐỀ NÓ GIẢI QUYẾT: trang Hồ sơ đếm "Đánh giá đã viết: 4" nhưng
     * con số đó là ngõ cụt — ba con số còn lại (đơn hàng, địa chỉ, yêu
     * thích) đều bấm vào được, riêng nó thì không.
     *
     * Trang Chính sách bảo mật hứa người dùng "gỡ đánh giá của chính
     * mình" được, và điều đó đúng — nhưng nút gỡ chỉ nằm trên TRANG SẢN
     * PHẨM. Ai viết bốn đánh giá cho bốn sản phẩm khác nhau phải nhớ ra
     * đủ bốn sản phẩm rồi mở từng trang. Với đánh giá viết từ nửa năm
     * trước thì đó là điều không làm nổi.
     *
     * KHÔNG VIẾT LẠI HÀM XOÁ. Trang này chỉ liệt kê và trỏ về đúng
     * route destroy() sẵn có — hai đường xoá là hai bộ phép kiểm quyền
     * phải giữ đồng bộ, và bộ thứ hai sẽ là bộ bị quên.
     */
    public function mine(): View
    {
        $reviews = Review::query()
            ->where('user_id', Auth::id())
            /*
             * Nạp sẵn sản phẩm: mỗi dòng đều hiện tên và ảnh sản phẩm,
             * không nạp thì mỗi dòng một truy vấn.
             *
             * withTrashed vì sản phẩm có thể đã bị cửa hàng gỡ khỏi
             * danh mục — đánh giá vẫn là của người dùng và họ vẫn phải
             * gỡ được, kể cả khi không còn trang sản phẩm để vào.
             */
            ->with(['product' => fn ($q) => $q->withTrashed()])
            ->latest()
            ->paginate(10);

        return view('shop.reviews.mine', compact('reviews'));
    }
}