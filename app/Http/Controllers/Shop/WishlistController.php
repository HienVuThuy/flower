<?php

namespace App\Http\Controllers\Shop;

use App\Enums\UserEventType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\UserEvent;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Danh sách yêu thích. Bắt buộc đăng nhập (xem chú thích ở migration).
 */
class WishlistController extends Controller
{
    public function index(): View
    {
        /*
         * Nạp sẵn những thứ card sản phẩm cần: khuyến mại để tính giá,
         * biến thể để biết còn hàng. Thiếu chỗ này là N+1 ngay trên trang
         * có nhiều sản phẩm nhất của khách.
         */
        $products = Product::query()
            ->whereHas('wishlists', fn ($q) => $q->where('user_id', Auth::id()))
            ->with(['promotions', 'variants', 'images'])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->latest('id')
            ->paginate(12);

        return view('shop.wishlist.index', [
            'products' => $products,
        ]);
    }

    /**
     * Bật/tắt yêu thích cho một sản phẩm.
     *
     * Một hành động thay vì store + destroy riêng: nút trên giao diện là
     * một cái tim bấm qua bấm lại, không phải hai nút khác nhau.
     *
     * firstOrCreate + delete dựa trên ràng buộc UNIQUE(user_id, product_id)
     * của bảng, nên hai lần bấm gần nhau cũng không tạo hai dòng.
     */
    public function toggle(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $existing = Wishlist::where('user_id', Auth::id())
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->delete();

            /*
             * CỐ Ý KHÔNG ghi sự kiện khi BỎ thích.
             *
             * Enum chỉ có một loại `wishlist`. Ghi cả lúc thêm lẫn lúc bỏ
             * bằng cùng một loại thì về sau không cách nào phân biệt được
             * hai hành vi trái ngược nhau — số liệu "sản phẩm được thích
             * nhiều nhất" sẽ đếm luôn cả những lần khách đổi ý.
             *
             * Guide §25 liệt kê `wishlist` như một TÍN HIỆU QUAN TÂM, nên
             * ở đây nó mang đúng nghĩa đó. Muốn theo dõi cả việc bỏ thích
             * thì phải thêm một loại sự kiện riêng, không dùng chung loại
             * này.
             */
            return $this->traLoi(
                $request,
                'Đã bỏ "' . $product->name . '" khỏi danh sách yêu thích.',
                active: false,
            );
        }

        Wishlist::create([
            'user_id' => Auth::id(),
            'product_id' => $product->id,
        ]);

        /*
         * Ghi SAU khi đã lưu thành công, không phải lúc khách bấm nút —
         * cùng quy ước với các sự kiện khác. Lưu hỏng thì không được có
         * sự kiện ma trong dữ liệu phân tích.
         *
         * UserEvent::log tự nuốt mọi lỗi bên trong, nên việc theo dõi
         * hành vi không bao giờ làm hỏng thao tác chính của khách.
         */
        UserEvent::log(UserEventType::Wishlist, $request, [
            'product_id' => $product->id,
            'category_id' => $product->category_id,
        ]);

        return $this->traLoi(
            $request,
            'Đã thêm "' . $product->name . '" vào danh sách yêu thích.',
            active: true,
        );
    }

    /**
     * Trả lời một lần bấm tim — HAI DẠNG, một luồng xử lý.
     *
     *   - Biểu mẫu gửi bình thường -> chuyển hướng back() kèm thông báo
     *   - JavaScript gọi bằng fetch -> JSON
     *
     * `active` là TRẠNG THÁI SAU KHI BẤM, do máy chủ nói ra, chứ không
     * để trình duyệt tự lật ngược cái nó đang hiển thị. Khách mở hai tab
     * cùng một sản phẩm, bấm thích ở tab này rồi bấm ở tab kia: nếu lật
     * theo trạng thái trên màn hình thì tab thứ hai hiện ngược hẳn với
     * dữ liệu thật. Máy chủ vừa ghi xong thì máy chủ biết đúng.
     */
    private function traLoi(Request $request, string $message, bool $active): RedirectResponse|JsonResponse
    {
        if (! $request->expectsJson()) {
            return back()->with('success', $message);
        }

        return response()->json([
            'ok' => true,
            'message' => $message,
            'active' => $active,
        ]);
    }
}
