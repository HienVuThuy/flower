<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\Recommendation\RecommendationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        private readonly RecommendationService $recommendations,
    ) {
    }

    public function index(Request $request): View
    {
        $categories = Category::query()
            // Trang chủ chỉ giới thiệu HÀNG CHÍNH — xem App\Enums\CategoryKind.
            ->plants()
            ->where('is_active', true)
            ->withCount([
                'products' => fn ($q) => $q->whereIn('status', ['active', 'out_of_stock']),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->take(4)
            ->get();

        /*
         * HÀNG MỚI VỀ — khối nổi bật đầu trang.
         *
         * Trước đây khối này mang tiêu đề "Được yêu thích gần đây" nhưng
         * truy vấn chỉ là latest() — không hề đụng bảng `wishlists`. Nay
         * tiêu đề nói đúng thứ nó hiện, và phần "được yêu thích" tách
         * thành khối riêng bên dưới, dựng từ dữ liệu thật.
         *
         * LẦN SỬA THỨ HAI, cùng một loại lỗi: `latest()` vẫn chỉ có
         * nghĩa "8 món thêm sau cùng", không có nghĩa "8 món mới". Cửa
         * hàng nghỉ nhập hàng ba tháng thì khối này vẫn trưng hàng quý
         * trước dưới chữ "Hàng mới về".
         *
         * `newArrivals()` giới hạn theo NGÀY và có quyền trả về rỗng —
         * xem config/catalog.php để biết vì sao chọn 60 ngày, và
         * welcome.blade.php để biết vì sao rỗng thì ẩn hẳn khối.
         */
        $featuredProducts = Product::query()
            ->with(['category', 'promotions',
                // Thẻ sản phẩm phải biết hàng này có quy cách hay
                // không để hiện đúng nút; hỏi từng thẻ là N+1.
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->mainCatalog()
            ->whereIn('status', ['active', 'out_of_stock'])
            ->newArrivals()
            ->take((int) config('catalog.new_arrival_limit', 8))
            ->get();

        /*
         * GỢI Ý CÁ NHÂN HOÁ (Guide §11 "User → Gợi ý cá nhân hoá").
         *
         * Truyền cả id người dùng lẫn id phiên: phần lớn người vào xem
         * chưa đăng nhập, nếu chỉ gợi ý cho người có tài khoản thì tính
         * năng gần như không bao giờ chạy.
         */
        $reco = $this->recommendations->forViewer(
            $request->user()?->id,
            $request->session()->getId(),
        );

        return view('welcome', [
            'categories' => $categories,
            'featuredProducts' => $featuredProducts,
            'mostWished' => $this->mostWished($reco['items']->pluck('product.id')->all()),
            'recommendations' => $reco['items'],
            'recommendationsArePersonal' => $reco['personalized'],
        ]);
    }

    /**
     * ĐƯỢC YÊU THÍCH GẦN ĐÂY — dựng từ bảng `wishlists` thật.
     * ============================================================
     * "GẦN ĐÂY" ĐƯỢC HIỂU ĐÚNG NGHĨA: xếp theo LẦN THÍCH MỚI NHẤT, không
     * phải theo tổng số lượt thích. Một sản phẩm được 50 người thích từ
     * năm ngoái không còn là tin tức; một sản phẩm được 3 người thích
     * sáng nay thì có.
     *
     * TRẢ VỀ RỖNG KHI CHƯA AI THÍCH GÌ, và Blade sẽ không render khối
     * đó. Đây là chỗ dễ bịa nhất trên cả trang chủ: rất dễ đổ đại vài
     * sản phẩm vào cho đỡ trống, và khách sẽ tin rằng chúng được yêu
     * thích. Thà thiếu một khối còn hơn một khối nói sai.
     *
     * @param  list<int>  $excludeIds  sản phẩm ĐANG hiện ở khối gợi ý ngay
     *                                 bên trên — không lặp lại
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function mostWished(array $excludeIds = []): \Illuminate\Support\Collection
    {
        return Product::query()
            ->with(['category', 'promotions',
                // Thẻ sản phẩm phải biết hàng này có quy cách hay
                // không để hiện đúng nút; hỏi từng thẻ là N+1.
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->withCount('wishlists')
            ->mainCatalog()
            ->whereIn('status', ['active', 'out_of_stock'])
            ->whereHas('wishlists')
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            /*
             * MAX(created_at) của lượt thích, không phải created_at của
             * sản phẩm. Cần subquery vì `withCount` chỉ đếm được, không
             * lấy được mốc thời gian.
             */
            ->addSelect(['last_wished_at' => \App\Models\Wishlist::query()
                ->selectRaw('MAX(created_at)')
                ->whereColumn('wishlists.product_id', 'products.id'),
            ])
            ->orderByDesc('last_wished_at')
            ->take(4)
            ->get();
    }
}
