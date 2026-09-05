<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cẩm nang — trang khách đọc.
 * ============================================================
 * MỌI TRUY VẤN ĐI QUA `scopePublished()`.
 *
 * Không viết `whereNotNull('published_at')` bằng tay ở đây: scope còn
 * kiểm cả `<= now()` cho bài đặt lịch, và một bản chép tay thiếu vế đó
 * sẽ để lộ bài chưa tới ngày đăng — im lặng, và chỉ phát hiện khi có
 * người thấy bài của tuần sau.
 */
class BlogController extends Controller
{
    /** Số bài mỗi trang. Ít thôi: bài dài, ảnh bìa to. */
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

            // Chuyên mục không tồn tại thì bỏ qua bộ lọc thay vì 404: URL
            // cũ trong một bài chia sẻ vẫn dẫn tới danh sách đầy đủ, hơn
            // là dẫn vào trang lỗi.
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

            /*
             * Bài nổi bật chỉ hiện ở TRANG ĐẦU và khi KHÔNG lọc chuyên
             * mục: ở trang 2 nó đã bị đọc lướt qua rồi, và trong một
             * chuyên mục thì "nổi bật" của toàn trang là lạc đề.
             *
             * Xét theo SỐ TRANG YÊU CẦU, không xét trạng thái của đối
             * tượng truy vấn. Bản đầu kiểm `$query->getQuery()->offset`,
             * nhưng `paginate()` ở dòng trên đã đặt offset lên chính đối
             * tượng đó — nên điều kiện luôn sai và khối nổi bật không bao
             * giờ hiện. Lỗi phụ thuộc vào THỨ TỰ các khoá trong mảng, thứ
             * không ai đọc code mà đoán ra được.
             */
            'noiBat' => (! $chuyenMuc && (int) $request->query('page', 1) === 1)
                ? BlogPost::published()->with('category:id,name,slug')
                    ->orderByDesc('view_count')->first()
                : null,
        ]);
    }

    public function show(Request $request, BlogPost $post): View
    {
        /*
         * BÀI CHƯA ĐĂNG -> 404, kể cả khi biết đúng slug.
         *
         * Route-model binding tìm theo slug và không biết gì về trạng
         * thái. Thiếu phép kiểm này thì ai đoán trúng slug là đọc được
         * bản nháp — và bản nháp là thứ chưa ai muốn cho đọc.
         */
        abort_unless($post->isPublished(), 404);

        $post->load(['category', 'author:id,name', 'products' => fn ($q) => $q
            ->where('status', 'active')
            ->with(['category:id,name,slug', 'promotions'])]);

        /*
         * ĐẾM LƯỢT XEM BẰNG `increment`, không phải đọc-rồi-ghi.
         *
         * `$post->view_count++; $post->save()` mất lượt khi hai người mở
         * cùng lúc: cả hai đọc cùng một số rồi cùng ghi số đó cộng một.
         * `increment` để cơ sở dữ liệu tự cộng, không có khoảng hở.
         *
         * Không đụng `updated_at` (`timestamps = false` ngầm qua
         * increment của Laravel) — nếu không thì mọi lượt xem đẩy bài lên
         * đầu danh sách "vừa cập nhật" ở trang quản trị.
         */
        $post->increment('view_count');

        return view('shop.blog.show', [
            'post' => $post,

            /*
             * Bài liên quan: cùng chuyên mục, mới nhất, trừ chính nó.
             *
             * Không dùng thuật toán "độ tương đồng nội dung": với vài
             * chục bài thì nó chỉ là một cách phức tạp để ra kết quả gần
             * như ngẫu nhiên. Cùng chuyên mục là tín hiệu thật và admin
             * kiểm soát được.
             */
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
