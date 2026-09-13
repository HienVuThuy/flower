<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Chuyên mục Cẩm nang.
 * ============================================================
 * VÌ SAO CẦN: trước đây ba chuyên mục đến từ dữ liệu mẫu và không có chỗ
 * nào thêm hay sửa. Viết một bài về "hoa cưới" mà không có chuyên mục hợp
 * thì chỉ còn cách nhét vào một chuyên mục sai.
 *
 * KHÔNG XOÁ CHUYÊN MỤC CÒN BÀI — kể cả bài đã xoá mềm: khôi phục bài đó
 * về sau là nó trỏ vào một chuyên mục không còn, và trang Cẩm nang lỗi.
 * Muốn bỏ chuyên mục thì chuyển bài sang chỗ khác trước.
 */
class BlogCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.blog.categories', [
            'chuyenMuc' => BlogCategory::query()
                ->withCount(['posts' => fn ($q) => $q->withTrashed()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->kiemTra($request);

        $cm = BlogCategory::create($data);

        return redirect()
            ->route('admin.blog-categories.index')
            ->with('success', 'Đã thêm chuyên mục "' . $cm->name . '".');
    }

    public function update(Request $request, BlogCategory $blogCategory): RedirectResponse
    {
        $data = $this->kiemTra($request, $blogCategory);

        $blogCategory->update($data);

        return redirect()
            ->route('admin.blog-categories.index')
            ->with('success', 'Đã sửa chuyên mục "' . $blogCategory->name . '".');
    }

    public function destroy(BlogCategory $blogCategory): RedirectResponse
    {
        $soBai = $blogCategory->posts()->withTrashed()->count();

        if ($soBai > 0) {
            return back()->with('error', sprintf(
                'Chuyên mục "%s" còn %d bài viết (tính cả bài đã xoá). Chuyển các bài đó sang chuyên mục khác trước.',
                $blogCategory->name,
                $soBai,
            ));
        }

        $ten = $blogCategory->name;
        $blogCategory->delete();

        return redirect()
            ->route('admin.blog-categories.index')
            ->with('success', 'Đã xoá chuyên mục "' . $ten . '".');
    }

    /**
     * Một bộ quy tắc cho cả thêm lẫn sửa.
     *
     * SLUG ĐỂ TRỐNG THÌ SINH TỪ TÊN. Slug là địa chỉ khách thấy
     * (/cam-nang?chuyen-muc=...), bắt người nhập gõ tay thì sớm muộn có
     * slug có dấu hoặc khoảng trắng.
     *
     * @return array<string, mixed>
     */
    private function kiemTra(Request $request, ?BlogCategory $hienTai = null): array
    {
        $request->merge([
            'slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name'))),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_categories', 'slug')->ignore($hienTai?->id),
            ],
            'description' => ['nullable', 'string', 'max:300'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ], [
            'slug.unique' => 'Đã có chuyên mục dùng địa chỉ này.',
            'slug.regex' => 'Địa chỉ chỉ gồm chữ thường không dấu, số và gạch nối.',
        ], [
            'name' => 'tên chuyên mục',
            'slug' => 'địa chỉ',
            'description' => 'mô tả',
            'sort_order' => 'thứ tự',
        ]) + ['sort_order' => 0];
    }
}
