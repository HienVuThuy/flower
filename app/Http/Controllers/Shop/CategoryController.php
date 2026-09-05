<?php

namespace App\Http\Controllers\Shop;

use App\Enums\UserEventType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\UserEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->withCount([
                'products' => fn ($query) => $query->whereIn('status', ['active', 'out_of_stock']),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('shop.categories.index', compact('categories'));
    }

    public function show(Request $request, Category $category): View
    {
        abort_unless($category->is_active, 404);

        UserEvent::log(UserEventType::CategoryView, $request, [
            'category_id' => $category->id,
        ]);

        // `with('category')` là bắt buộc: x-product.card đọc
        // $product->category->name, thiếu eager load sẽ sinh thêm
        // một query cho MỖI sản phẩm trên trang (N+1).
        $products = $category->products()
            ->with(['category', 'promotions',
                // Thẻ sản phẩm phải biết hàng này có quy cách hay
                // không để hiện đúng nút; hỏi từng thẻ là N+1.
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->whereIn('status', ['active', 'out_of_stock'])
            ->latest()
            ->paginate(12);

        return view('shop.categories.show', compact('category', 'products'));
    }
}
