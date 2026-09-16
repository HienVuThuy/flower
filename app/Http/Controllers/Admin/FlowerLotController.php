<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FlowerLotStatus;
use App\Enums\FlowerQuality;
use App\Enums\FlowerUnit;
use App\Http\Controllers\Controller;
use App\Models\FlowerKind;
use App\Models\FlowerLot;
use App\Models\Supplier;
use App\Services\Inventory\FlowerLotException;
use App\Services\Inventory\FlowerLotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Lô hoa: một lần lấy hàng. */
class FlowerLotController extends Controller
{
    public function __construct(
        private readonly FlowerLotService $service,
    ) {
    }

    public function index(Request $request): View
    {
        $q = FlowerLot::query()
            ->with(['kind', 'supplier'])
            ->orderByRaw("CASE WHEN status = 'dang_dung' THEN 0 ELSE 1 END")
            ->orderByDesc('purchased_at')
            ->orderByDesc('id');

        if ($tt = $request->query('trang_thai')) {
            $q->where('status', $tt);
        }

        if ($loai = (int) $request->query('loai_hoa')) {
            $q->where('flower_kind_id', $loai);
        }

        if ($ncc = (int) $request->query('nha_cung_cap')) {
            $q->where('supplier_id', $ncc);
        }

        return view('admin.flower-lots.index', [
            'lo' => $q->paginate(25)->withQueryString(),
            'loaiHoa' => FlowerKind::orderBy('name')->get(),
            'nhaCungCap' => Supplier::orderBy('name')->get(['id', 'name']),
            'trangThai' => FlowerLotStatus::cases(),

            'quenDong' => $this->service->loQuenDong(),
            'ngayNhac' => FlowerLotService::NGAY_NHAC_DONG,
        ]);
    }

    public function create(): View
    {
        return view('admin.flower-lots.create', $this->duLieuBieuMau(null));
    }

    public function edit(FlowerLot $flowerLot): View|RedirectResponse
    {
        if (! FlowerLotService::conSuaDuoc($flowerLot)) {
            return redirect()
                ->route('admin.flower-lots.index')
                ->with('error', 'Lô ' . $flowerLot->code . ' đã đóng hoặc đã ghi trả hàng nên không sửa được.');
        }

        return view('admin.flower-lots.create', $this->duLieuBieuMau($flowerLot));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->quyTac(), [], $this->tenTruong());

        $ncc = isset($data['supplier_id']) ? Supplier::find($data['supplier_id']) : null;

        $lo = new FlowerLot();

        $lo->forceFill(array_merge($data, [
            'code' => $this->service->sinhMa(),
            'supplier_id' => $ncc?->id,
            'supplier_name' => $ncc?->name,
            'status' => FlowerLotStatus::DangDung,
            'created_by' => Auth::id(),
        ]))->save();

        return redirect()
            ->route('admin.flower-lots.index')
            ->with('success', 'Đã ghi lô ' . $lo->code . '. Dùng hết thì nhớ đóng lô — chưa đóng thì tiền chưa vào giá vốn.');
    }

    public function update(Request $request, FlowerLot $flowerLot): RedirectResponse
    {
        $data = $request->validate($this->quyTac(), [], $this->tenTruong());

        $ncc = isset($data['supplier_id']) ? Supplier::find($data['supplier_id']) : null;

        try {
            $this->service->capNhatLo($flowerLot, array_merge($data, [
                'supplier_id' => $ncc?->id,
                'supplier_name' => $ncc?->name,
                'quality' => $data['quality'] ?? null,
                'note' => $data['note'] ?? null,
            ]));
        } catch (FlowerLotException $e) {
            return redirect()->route('admin.flower-lots.index')->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.flower-lots.index')
            ->with('success', 'Đã sửa lô ' . $flowerLot->code . '.');
    }

    public function destroy(FlowerLot $flowerLot): RedirectResponse
    {
        try {
            $this->service->xoaLo($flowerLot);
        } catch (FlowerLotException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.flower-lots.index')
            ->with('success', 'Đã xoá lô ' . $flowerLot->code . '.');
    }

    private function duLieuBieuMau(?FlowerLot $lo): array
    {
        return [
            'lo' => $lo,
            'loaiHoa' => FlowerKind::query()
                ->where(fn ($q) => $q->dangDung()->when($lo, fn ($q) => $q->orWhere('id', $lo->flower_kind_id)))
                ->orderBy('name')
                ->get(),
            'nhaCungCap' => Supplier::query()
                ->where(fn ($q) => $q->dangHoatDong()->when($lo?->supplier_id, fn ($q) => $q->orWhere('id', $lo->supplier_id)))
                ->orderBy('name')
                ->get(['id', 'name', 'kind']),
            'donVi' => FlowerUnit::cases(),
        ];
    }

    private function quyTac(): array
    {
        return [
            'flower_kind_id' => ['required', 'integer', 'exists:flower_kinds,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'purchased_at' => ['required', 'date', 'before_or_equal:today'],

            'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'unit' => ['required', Rule::enum(FlowerUnit::class)],

            'total_cost' => ['required', 'numeric', 'gt:0', 'max:999999999'],

            'quality' => ['nullable', Rule::enum(FlowerQuality::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function tenTruong(): array
    {
        return [
            'flower_kind_id' => 'loại hoa',
            'purchased_at' => 'ngày lấy hàng',
            'quantity' => 'số lượng',
            'total_cost' => 'tổng tiền',
        ];
    }

    public function close(Request $request, FlowerLot $flowerLot): RedirectResponse
    {
        $data = $request->validate([
            'hao_hut' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'quality' => ['nullable', Rule::enum(FlowerQuality::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [], ['hao_hut' => 'hao hụt']);

        try {
            $this->service->dongLo(
                $flowerLot,
                $data['hao_hut'] ?? 0,
                $data['quality'] ?? null,
                $data['note'] ?? null,
            );
        } catch (FlowerLotException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã đóng lô ' . $flowerLot->code . '.');
    }
}
