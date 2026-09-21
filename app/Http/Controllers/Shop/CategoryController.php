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
            ->whereHas('products', fn ($q) => $q->whereIn('status', ['active', 'out_of_stock']))
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

        $products = $category->products()
            ->with(['category', 'promotions',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->whereIn('status', ['active', 'out_of_stock'])
            ->latest()
            ->paginate(12);

        return view('shop.categories.show', compact('category', 'products'));
    }
}
