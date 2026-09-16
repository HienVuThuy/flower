<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Cẩm nang — trang khách đọc. */
class BlogController extends Controller
{
    private const MOI_TRANG = 9;

    public function index(Request $request): View
    {
        $chuyenMuc = $request->query('chuyen-muc');

        $query = BlogPost::query()
            ->published()
            ->with(['category:id,name,slug', 'author:id,name'])
            ->latest('published_at');

        $dangLoc = null;

        if ($chuyenMuc) {
            $dangLoc = BlogCategory::where('slug', $chuyenMuc)->first();

            if ($dangLoc) {
                $query->where('blog_category_id', $dangLoc->id);
            }
        }

        return view('shop.blog.index', [
            'posts' => $query->paginate(self::MOI_TRANG)->withQueryString(),
            'categories' => BlogCategory::orderBy('sort_order')->orderBy('name')
                ->withCount(['posts' => fn ($q) => $q->published()])
                ->get(),
            'dangLoc' => $dangLoc,

            'noiBat' => (! $chuyenMuc && (int) $request->query('page', 1) === 1)
                ? BlogPost::published()->with('category:id,name,slug')
                    ->orderByDesc('view_count')->first()
                : null,
        ]);
    }

    public function show(Request $request, BlogPost $post): View
    {
        abort_unless($post->isPublished(), 404);

        $post->load(['category', 'author:id,name', 'products' => fn ($q) => $q
            ->where('status', 'active')
            ->with(['category:id,name,slug', 'promotions'])]);

        $post->increment('view_count');

        return view('shop.blog.show', [
            'post' => $post,

            'lienQuan' => BlogPost::published()
                ->where('id', '!=', $post->id)
                ->when($post->blog_category_id, fn ($q) => $q->where('blog_category_id', $post->blog_category_id))
                ->with('category:id,name,slug')
                ->latest('published_at')
                ->limit(3)
                ->get(),
        ]);
    }
}
