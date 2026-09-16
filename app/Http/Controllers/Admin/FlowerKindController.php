<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FlowerUnit;
use App\Http\Controllers\Controller;
use App\Models\FlowerKind;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Danh sách loại hoa thu mua — thứ người ta gọi tên khi ra chợ. */
class FlowerKindController extends Controller
{
    public function index(): View
    {
        return view('admin.flower-kinds.index', [
            'loaiHoa' => FlowerKind::withCount('lots')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(30),
            'donVi' => FlowerUnit::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->duLieu($request);

        FlowerKind::create($data);

        return back()->with('success', 'Đã thêm loại hoa ' . $data['name'] . '.');
    }

    public function update(Request $request, FlowerKind $flowerKind): RedirectResponse
    {
        $flowerKind->update($this->duLieu($request, $flowerKind));

        return back()->with('success', 'Đã lưu ' . $flowerKind->name . '.');
    }

    private function duLieu(Request $request, ?FlowerKind $dangSua = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('flower_kinds', 'name')->ignore($dangSua?->id),
            ],
            'default_unit' => ['required', Rule::enum(FlowerUnit::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.unique' => 'Đã có loại hoa tên này. Sửa dòng cũ thay vì thêm dòng mới — hai dòng cùng một loại thì không so giá được.',
        ], [
            'name' => 'tên loại hoa',
            'default_unit' => 'đơn vị mặc định',
        ]);

        $data['name'] = trim($data['name']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
