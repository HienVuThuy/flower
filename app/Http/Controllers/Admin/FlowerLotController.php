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

/**
 * Lô hoa: một lần lấy hàng.
 * ============================================================
 * KHÔNG SỬA, KHÔNG XOÁ LÔ ĐÃ ĐÓNG.
 *
 * Lô đã đóng là một con số đã đi vào giá vốn của một kỳ. Sửa nó là sửa
 * lại một báo cáo đã đọc — cùng nguyên tắc với phiếu nhập đã ghi sổ và
 * phiếu hoàn tiền.
 */
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

            /*
             * NHẮC LÔ QUÊN ĐÓNG NGAY TRÊN DANH SÁCH.
             *
             * Quên đóng lô là giá vốn hoa thấp hơn sự thật và lãi gộp cao
             * hơn sự thật — sai theo hướng dễ chịu, tức là hướng không ai
             * tự đi tìm.
             */
            'quenDong' => $this->service->loQuenDong(),
            'ngayNhac' => FlowerLotService::NGAY_NHAC_DONG,
        ]);
    }

    public function create(): View
    {
        return view('admin.flower-lots.create', [
            'loaiHoa' => FlowerKind::dangDung()->orderBy('name')->get(),
            'nhaCungCap' => Supplier::dangHoatDong()->orderBy('name')->get(['id', 'name', 'kind']),
            'donVi' => FlowerUnit::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'flower_kind_id' => ['required', 'integer', 'exists:flower_kinds,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'purchased_at' => ['required', 'date', 'before_or_equal:today'],

            /*
             * SỐ LƯỢNG > 0 và CÓ PHẦN THẬP PHÂN.
             *
             * Mua theo cân thì 3,5kg là chuyện thường. Ép số nguyên là ép
             * người dùng làm tròn, và cái làm tròn đó đi thẳng vào giá
             * vốn mỗi đơn vị.
             */
            'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'unit' => ['required', Rule::enum(FlowerUnit::class)],

            // Tiền phải > 0: lô 0 đồng là "được cho", nói ra bằng ghi chú
            // chứ không bằng một con số làm lệch giá bình quân.
            'total_cost' => ['required', 'numeric', 'gt:0', 'max:999999999'],

            'quality' => ['nullable', Rule::enum(FlowerQuality::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'flower_kind_id' => 'loại hoa',
            'purchased_at' => 'ngày lấy hàng',
            'quantity' => 'số lượng',
            'total_cost' => 'tổng tiền',
        ]);

        $ncc = isset($data['supplier_id']) ? Supplier::find($data['supplier_id']) : null;

        $lo = new FlowerLot();

        // `code`, `status`, `created_by` không nằm trong $fillable.
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
