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
            $data['image'] = $this->anh->luu($request->file('image'), 'categories');
        }

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

        $this->anh->xoa($category->image);

        $this->logCrud('category.deleted', $category, 'danh mục', $category->name);

        $category->delete();

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Xóa danh mục thành công.');
    }
}