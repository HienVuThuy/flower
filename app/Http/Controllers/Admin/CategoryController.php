<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\Media\ImageStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CategoryController extends Controller
{
    use LogsAdminActivity;

    public function __construct(private readonly ImageStore $anh)
    {
    }

    public function index(Request $request): View
    {
        $categories = Category::query()
            ->withCount('products')
            ->when($request->filled('q'), fn ($q) => $q
                ->where('name', 'like', '%'.trim((string) $request->query('q')).'%'))

            ->when(
                $request->filled('kind'),
                fn ($q) => $q->where('kind', $request->string('kind'))
            )

            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.categories.create');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            // ImageStore chứ không $file->store(): nó lưu XONG rồi sinh
            // luôn bản WebP. Gọi store() thẳng là ảnh mới không bao giờ
            // được tối ưu — xem chú thích ở ImageStore.
            $data['image'] = $this->anh->luu($request->file('image'), 'categories');
        }

        // Không chọn nhóm thì cột `kind` tự lấy 'plant' — mặc định nằm ở migration, không chép lại ở đây.
        if (empty($data['kind'])) {
            unset($data['kind']);
        }

        $category = Category::create($data);

        $this->logCrud('category.created', $category, 'danh mục', $category->name);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Thêm danh mục thành công.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', compact('category'));
    }

    public function update(
    UpdateCategoryRequest $request,
    Category $category
) {
    $data = $request->validated();

    /*
     * ĐỔI NHÓM DANH MỤC — chỉ khi mọi sản phẩm đang ở trong vẫn hợp.
     *
     * Đổi "Chậu cảnh" từ hoa & cây sang phụ kiện mà trong đó còn cây thật
     * là cây hiện lẫn giữa đất trồng và bình xịt ở trang /phu-kien. Cùng
     * luật với lúc gán sản phẩm vào danh mục (ProductType::fitsCategoryKind).
     */
    if (empty($data['kind'])) {
        unset($data['kind']);
    } else {
        $nhomMoi = \App\Enums\CategoryKind::from($data['kind']);
        $khongHop = $category->products()->withTrashed()->get(['product_type'])
            ->filter(fn ($sp) => ! $sp->product_type?->fitsCategoryKind($nhomMoi))
            ->count();

        if ($khongHop > 0) {
            return back()->withInput()->withErrors(['kind' => sprintf(
                'Còn %d sản phẩm trong danh mục không thuộc nhóm "%s". Chuyển chúng sang danh mục khác trước.',
                $khongHop,
                $nhomMoi->label(),
            )]);
        }
    }

    if ($request->hasFile('image')) {

        // xoa() dọn cả bản WebP cũ, không chỉ ảnh gốc.
        $this->anh->xoa($category->image);

        $data['image'] = $this->anh->luu($request->file('image'), 'categories');
    }

    $category->update($data);

    $this->logCrud('category.updated', $category, 'danh mục', $category->name);

    return redirect()
        ->route('admin.categories.index')
        ->with('success', 'Cập nhật danh mục thành công.');
}

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return redirect()
                ->route('admin.categories.index')
                ->with('error', 'Không thể xóa danh mục đang có sản phẩm.');
        }

        // xoa() dọn cả bản WebP và các cỡ ảnh, không chỉ ảnh gốc.
        $this->anh->xoa($category->image);

        /*
         * GHI NHẬT KÝ TRƯỚC KHI XOÁ.
         *
         * Sau khi xoá thì $category->name vẫn còn trong bộ nhớ nên
         * vẫn ghép câu được, nhưng khoá chính đã không còn trỏ tới
         * đâu — subject_id thành một số chết. Ghi trước thì dòng
         * nhật ký giữ đúng id của thứ vừa biến mất.
         */
        $this->logCrud('category.deleted', $category, 'danh mục', $category->name);

        $category->delete();

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Xóa danh mục thành công.');
    }
}