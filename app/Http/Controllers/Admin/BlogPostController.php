<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Product;
use App\Services\Blog\BlogImageLibrary;
use App\Services\Media\HtmlSanitizer;
use App\Services\Media\ImageStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Quản trị Cẩm nang.
 * ⚠️ NỘI DUNG BÀI ĐƯỢC IN RA TRANG DƯỚI DẠNG HTML THÔ.
 */
class BlogPostController extends Controller
{
    public function __construct(
        private readonly BlogImageLibrary $thuVienAnh,
    ) {
    }

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
        $this->capNhatAnhBai($post, $request);

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
        $this->capNhatAnhBai($post, $request);

        return redirect()
            ->route('admin.blog.edit', $post)
            ->with('success', 'Đã lưu thay đổi.');
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $ten = $post->title;

        $this->thuVienAnh->xoaTatCa($post);

        $post->delete();

        return redirect()
            ->route('admin.blog.index')
            ->with('success', 'Đã xoá bài "' . $ten . '".');
    }

    private function kiemTra(Request $request, ?BlogPost $post = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'blog_category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string'],
            'cover_image' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],

            'anh_bai' => ['nullable', 'array', 'max:12'],
            'anh_bai.*' => ['file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'chu_thich' => ['nullable', 'array'],
            'chu_thich.*' => ['nullable', 'string', 'max:200'],
            'xoa_anh' => ['nullable', 'array'],
            'xoa_anh.*' => ['integer'],
            'meta_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:300'],

            'published_at' => ['nullable', 'date'],

            'products' => ['nullable', 'array', 'max:12'],
            'products.*.id' => ['nullable', 'integer', 'exists:products,id'],
            'products.*.note' => ['nullable', 'string', 'max:200'],
        ], [], [
            'title' => 'tiêu đề',
            'body' => 'nội dung',
            'cover_image' => 'ảnh bìa',
            'anh_bai.*' => 'ảnh trong bài',
            'published_at' => 'ngày đăng',
        ]);

        $data = array_replace($data, \App\Services\Time\Gio::doiONhap($data, 'published_at'));

        $data['body'] = app(HtmlSanitizer::class)->lamSach($data['body']);

        $data['slug'] = $post?->slug ?: $this->slugDuyNhat($data['title']);

        unset($data['cover_image'], $data['products'], $data['anh_bai'], $data['chu_thich'], $data['xoa_anh']);

        return $data;
    }

    /** Thư viện ảnh của bài: thêm ảnh mới, sửa chú thích, xoá ảnh đã tích chọn. */
    private function capNhatAnhBai(BlogPost $post, Request $request): void
    {
        $xoa = array_map('intval', (array) $request->input('xoa_anh', []));

        if ($xoa !== []) {
            $this->thuVienAnh->xoa($post, $xoa);
        }

        $this->thuVienAnh->datChuThich($post, (array) $request->input('chu_thich', []));

        $this->thuVienAnh->them($post, (array) $request->file('anh_bai', []));
    }

    private function slugDuyNhat(string $tieuDe): string
    {
        $goc = Str::slug($tieuDe) ?: 'bai-viet';
        $slug = $goc;
        $i = 2;

        while (BlogPost::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $goc . '-' . $i++;
        }

        return $slug;
    }

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

    private function ganSanPham(BlogPost $post, Request $request): void
    {
        $rows = [];
        $thuTu = 0;

        foreach ((array) $request->input('products', []) as $row) {
            $id = (int) ($row['id'] ?? 0);

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

    private function sanPhamChon(): \Illuminate\Support\Collection
    {
        return Product::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
