<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupplierKind;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Danh sách nơi cửa hàng lấy hàng.
 * ============================================================
 * KHÔNG CÓ NÚT XOÁ.
 *
 * Phiếu nhập cũ trỏ tới đây. Xoá một nhà cung cấp là làm mất dấu vết
 * những lần đã mua của họ — và đó đúng là thứ người ta giữ sổ để có.
 * Ngừng làm ăn thì tắt đi: không hiện ở ô chọn nữa, lịch sử vẫn đọc
 * được.
 */
class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $q = Supplier::query()
            ->withCount('receipts')
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

    /**
     * @return array<string, mixed>
     */
    private function duLieu(Request $request, ?Supplier $dangSua = null): array
    {
        $data = $request->validate([
            /*
             * TÊN KHÔNG TRÙNG — đây là cả lý do bảng này tồn tại.
             *
             * Cho trùng thì lại quay về mớ hỗn độn của ô chữ tự do, chỉ
             * khác là lần này có id: "Vựa Hoa Tươi" và "Vựa hoa tươi"
             * thành hai dòng, và so giá giữa chúng thành vô nghĩa.
             */
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

        /*
         * LỚP THỨ HAI, CÓ CHỦ Ý — và đã kiểm là nó đang là lớp thứ hai.
         *
         * Middleware `TrimStrings` của Laravel đã cắt khoảng trắng mọi ô
         * chữ trước khi tới đây; phép đột biến bỏ dòng này đi mà bài kiểm
         * thử vẫn xanh, đúng như vậy.
         *
         * Vẫn giữ: quy tắc "không hai dòng cùng một tên" đứng hay đổ hoàn
         * toàn dựa vào việc tên đã được cắt. Để nó phụ thuộc vào một
         * middleware toàn cục mà chỗ này không nói gì là đặt một quy tắc
         * quan trọng lên một thứ ai cũng có thể tắt mà không biết mình
         * vừa làm gì.
         */
        $data['name'] = trim($data['name']);

        /*
         * Ô đánh dấu không gửi gì lên khi bỏ tích, nên phải đặt lại —
         * không thì lần lưu sau `is_active` vắng mặt và cột giữ giá trị
         * cũ: tắt mà không tắt được.
         */
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
