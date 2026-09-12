<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Product;
use App\Services\Media\HtmlSanitizer;
use App\Services\Media\ImageStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Quản trị Cẩm nang.
 * ============================================================
 * ⚠️ NỘI DUNG BÀI ĐƯỢC IN RA TRANG DƯỚI DẠNG HTML THÔ.
 *
 * Đó là chỗ duy nhất trong dự án làm vậy, và nó chỉ an toàn nhờ hai điều
 * kiện — cả hai đều nằm ở đây:
 *
 *   1. Route ghi nằm sau `role:admin`. Đây không phải nội dung người lạ
 *      gửi lên.
 *   2. Nội dung đi qua `HtmlSanitizer` LÚC LƯU, không phải lúc hiện.
 *      Làm sạch lúc hiện thì mỗi chỗ in bài phải nhớ gọi, và chỗ thứ ba
 *      sẽ quên.
 *
 * Bỏ bước làm sạch ở đây là mở một lỗ XSS mà không có lớp nào phía sau
 * đỡ.
 */
class BlogPostController extends Controller
{
    public function index(Request $request): View
    {
        $posts = BlogPost::query()
            ->with(['category:id,name', 'author:id,name'])
            ->when($request->query('q'), fn ($q, $tu) => $q->where('title', 'like', "%{$tu}%"))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.blog.index', [
            'posts' => $posts,
            'q' => $request->query('q'),
        ]);
    }

    public function create(): View
    {
        return view('admin.blog.form', [
            'post' => new BlogPost(),
            'categories' => BlogCategory::orderBy('sort_order')->orderBy('name')->get(),
            'products' => $this->sanPhamChon(),
            'daChon' => collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->kiemTra($request);

        $post = new BlogPost($data);
        $post->author_id = Auth::id();
        $post->cover_image = $this->anhBia($request, null);
        $post->save();

        $this->ganSanPham($post, $request);

        return redirect()
            ->route('admin.blog.edit', $post)
            ->with('success', 'Đã tạo bài "' . $post->title . '".');
    }

    public function edit(BlogPost $post): View
    {
        return view('admin.blog.form', [
            'post' => $post,
            'categories' => BlogCategory::orderBy('sort_order')->orderBy('name')->get(),
            'products' => $this->sanPhamChon(),
            'daChon' => $post->products()->get()->keyBy('id'),
        ]);
    }

    public function update(Request $request, BlogPost $post): RedirectResponse
    {
        $post->fill($this->kiemTra($request, $post));
        $post->cover_image = $this->anhBia($request, $post->cover_image);
        $post->save();

        $this->ganSanPham($post, $request);

        return redirect()
            ->route('admin.blog.edit', $post)
            ->with('success', 'Đã lưu thay đổi.');
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        /*
         * XOÁ MỀM (`SoftDeletes`), khác với nhật ký cá nhân.
         *
         * Bài viết là tài sản của cửa hàng và có thể đang được dẫn link
         * từ nơi khác. Một cú bấm nhầm không nên làm mất công sức vài giờ
         * viết, và ảnh bìa cũng giữ lại vì bài còn khôi phục được.
         *
         * Nhật ký thì ngược lại — xoá thật, vì đó là dữ liệu riêng tư mà
         * người dùng chủ động yêu cầu xoá (QĐ-123).
         */
        $ten = $post->title;
        $post->delete();

        return redirect()
            ->route('admin.blog.index')
            ->with('success', 'Đã xoá bài "' . $ten . '".');
    }

    /* ================= NỘI BỘ ================= */

    /** @return array<string, mixed> */
    private function kiemTra(Request $request, ?BlogPost $post = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'blog_category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string'],
            'cover_image' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'meta_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:300'],

            /*
             * `published_at` nhận cả ngày ở TƯƠNG LAI — đó là tính năng
             * đặt lịch đăng, không phải lỗi. Khác hẳn `entry_date` của
             * nhật ký, thứ ghi lại điều đã quan sát được nên không thể ở
             * tương lai (QĐ-126).
             */
            'published_at' => ['nullable', 'date'],

            'products' => ['nullable', 'array', 'max:12'],
            'products.*.id' => ['nullable', 'integer', 'exists:products,id'],
            'products.*.note' => ['nullable', 'string', 'max:200'],
        ], [], [
            'title' => 'tiêu đề',
            'body' => 'nội dung',
            'cover_image' => 'ảnh bìa',
            'published_at' => 'ngày đăng',
        ]);

        /*
         * GIỜ ĐĂNG là giờ trên đồng hồ người biên tập, không phải giờ lưu.
         *
         * Ô `datetime-local` gửi lên "2026-09-10T08:00" mà không kèm múi
         * giờ. Cất thẳng vào cột là hẹn đăng lúc 15h thay vì 8h sáng — và
         * bài viết nằm im suốt buổi sáng mà không ai hiểu vì sao.
         */
        $data = array_replace($data, \App\Services\Time\Gio::doiONhap($data, 'published_at'));

        /*
         * LÀM SẠCH HTML — bước không được bỏ. Xem chú thích đầu lớp.
         */
        $data['body'] = app(HtmlSanitizer::class)->lamSach($data['body']);

        /*
         * SLUG SINH MỘT LẦN rồi KHÔNG đổi theo tiêu đề.
         *
         * Sửa tiêu đề mà slug đổi theo là làm chết mọi link đã chia sẻ và
         * mọi thứ hạng Google đã có — đúng thứ cả khu vực này sinh ra để
         * xây. Admin đổi được slug bằng tay nếu thật sự cần, nhưng nó
         * không tự đổi sau lưng họ.
         */
        $data['slug'] = $post?->slug ?: $this->slugDuyNhat($data['title']);

        unset($data['cover_image'], $data['products']);

        return $data;
    }

    private function slugDuyNhat(string $tieuDe): string
    {
        $goc = Str::slug($tieuDe) ?: 'bai-viet';
        $slug = $goc;
        $i = 2;

        // `withTrashed`: bài đã xoá mềm vẫn giữ slug, nên tạo bài mới
        // trùng tên sẽ đụng ràng buộc UNIQUE nếu không tính tới nó.
        while (BlogPost::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $goc . '-' . $i++;
        }

        return $slug;
    }

    /** Ba trạng thái: ảnh mới, giữ ảnh cũ, bỏ ảnh — xem QĐ-153. */
    private function anhBia(Request $request, ?string $hienTai): ?string
    {
        if ($request->hasFile('cover_image')) {
            if ($hienTai) {
                app(ImageStore::class)->xoa($hienTai);
            }

            return app(ImageStore::class)->luu($request->file('cover_image'), 'blog');
        }

        if ($request->boolean('remove_cover') && $hienTai) {
            app(ImageStore::class)->xoa($hienTai);

            return null;
        }

        return $hienTai;
    }

    /**
     * Gán danh sách sản phẩm nhắc trong bài.
     *
     * `sync` với mảng đầy đủ: bỏ tích một sản phẩm ở biểu mẫu phải gỡ nó
     * khỏi bài. Dùng `attach` thì danh sách chỉ dài thêm mãi.
     */
    private function ganSanPham(BlogPost $post, Request $request): void
    {
        $rows = [];
        $thuTu = 0;

        foreach ((array) $request->input('products', []) as $row) {
            $id = (int) ($row['id'] ?? 0);

            // Hàng chưa chọn sản phẩm thì bỏ qua, KHÔNG báo lỗi — cùng
            // nguyên tắc với hàng chỉ số trống ở nhật ký (QĐ-128).
            if ($id <= 0) {
                continue;
            }

            $rows[$id] = [
                'note' => trim((string) ($row['note'] ?? '')) ?: null,
                'sort_order' => $thuTu++,
            ];
        }

        $post->products()->sync($rows);
    }

    /** @return \Illuminate\Support\Collection<int, Product> */
    private function sanPhamChon(): \Illuminate\Support\Collection
    {
        return Product::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
