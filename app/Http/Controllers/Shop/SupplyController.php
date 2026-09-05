<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\Recommendation\RecommendationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Trang "Phụ kiện & vật tư chăm sóc".
 * ============================================================
 * VÌ SAO TÁCH KHỎI TRANG SẢN PHẨM:
 * Đây là cửa hàng hoa và cây cảnh. Chậu, đất, phân bón đều cần, nhưng
 * không ai vào đây để mua một gói đất — họ mua đất VÌ vừa mua một cái
 * cây. Trộn chung một trang thì hoa phải chia chỗ với vật tư ngay ở nơi
 * quan trọng nhất, và bộ lọc "Hình thức bán" (bó / chậu / giỏ) đầy những
 * mục vô nghĩa với một chai thuốc trị nấm.
 *
 * TRANG NÀY CỐ Ý ĐƠN GIẢN HƠN trang sản phẩm chính: không có bộ lọc
 * phong thuỷ, không có độ khó chăm, không có tìm kiếm mờ. Người mua vật
 * tư đã biết mình cần gì — họ cần tìm nhanh, không cần được tư vấn.
 *
 * Xem App\Enums\CategoryKind về trục phân loại đứng sau việc tách này.
 */
class SupplyController extends Controller
{
    public function __construct(
        private readonly RecommendationService $recommendations,
    ) {
    }

    public function index(Request $request): View
    {
        $query = Product::query()
            ->with(['category', 'promotions',
                // Thẻ sản phẩm phải biết hàng này có quy cách hay
                // không để hiện đúng nút; hỏi từng thẻ là N+1.
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->supplyCatalog()
            ->whereIn('status', ['active', 'out_of_stock']);

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->string('category')));
        }

        /*
         * Sắp theo GIÁ TĂNG DẦN làm mặc định, không phải "mới nhất".
         *
         * Vật tư là hàng tiêu hao mua lại nhiều lần; người mua quan tâm
         * giá hơn là quan tâm hàng nào mới về. Trang sản phẩm chính thì
         * ngược lại — hoa mới về là tin đáng xem.
         */
        match ($request->string('sort')->toString()) {
            'price_desc' => $query->orderByEffectivePrice('desc'),
            'newest' => $query->latest(),
            default => $query->orderByEffectivePrice('asc'),
        };

        $products = $query->paginate(12)->withQueryString();

        return view('shop.supplies.index', [
            'products' => $products,
            'categories' => Category::query()
                ->supplies()
                ->where('is_active', true)
                ->withCount([
                    'products' => fn ($q) => $q->whereIn('status', ['active', 'out_of_stock']),
                ])
                ->orderBy('sort_order')
                ->get(),
        ]);
    }
}
