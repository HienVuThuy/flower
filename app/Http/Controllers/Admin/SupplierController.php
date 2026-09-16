<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupplierKind;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Danh sách nơi cửa hàng lấy hàng. */
class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $q = Supplier::query()
            ->withCount([
                'receipts' => fn ($q) => $q->where('kind', \App\Enums\StockReceiptKind::NhapMoi->value),
                'flowerLots',
            ])
            ->orderByDesc('is_active')
            ->orderBy('name');

        if ($loai = $request->query('loai')) {
            $q->where('kind', $loai);
        }

        if ($tim = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w
                ->where('name', 'like', '%' . $tim . '%')
                ->orWhere('phone', 'like', '%' . $tim . '%')
                ->orWhere('address', 'like', '%' . $tim . '%'));
        }

        return view('admin.suppliers.index', [
            'nhaCungCap' => $q->paginate(20)->withQueryString(),
            'cacLoai' => SupplierKind::cases(),
        ]);
    }

    public function create(): View
    {
        return view('admin.suppliers.form', [
            'nhaCungCap' => new Supplier(),
            'cacLoai' => SupplierKind::cases(),
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        return view('admin.suppliers.form', [
            'nhaCungCap' => $supplier,
            'cacLoai' => SupplierKind::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $supplier = Supplier::create($this->duLieu($request));

        return redirect()
            ->route('admin.suppliers.index')
            ->with('success', 'Đã thêm ' . $supplier->name . '.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->duLieu($request, $supplier));

        return redirect()
            ->route('admin.suppliers.index')
            ->with('success', 'Đã lưu ' . $supplier->name . '.');
    }

    private function duLieu(Request $request, ?Supplier $dangSua = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:160',
                Rule::unique('suppliers', 'name')->ignore($dangSua?->id),
            ],
            'kind' => ['required', Rule::enum(SupplierKind::class)],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.unique' => 'Đã có nhà cung cấp tên này. Sửa dòng cũ thay vì thêm dòng mới — hai dòng cùng một người thì không so giá được.',
        ], [
            'name' => 'tên',
            'kind' => 'loại nguồn hàng',
            'phone' => 'số điện thoại',
            'address' => 'địa chỉ',
            'note' => 'ghi chú',
        ]);

        $data['name'] = trim($data['name']);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
