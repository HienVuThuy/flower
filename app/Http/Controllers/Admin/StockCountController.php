<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StockCountStatus;
use App\Http\Controllers\Controller;
use App\Models\StockCount;
use App\Services\Inventory\InventoryException;
use App\Services\Inventory\StockCountService;
use App\Services\Inventory\StockUnits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Phiếu kiểm kê kho. */
class StockCountController extends Controller
{
    public function __construct(
        private readonly StockCountService $service,
    ) {
    }

    public function index(Request $request): View
    {
        $trangThai = StockCountStatus::tryFrom((string) $request->query('trang-thai', ''));

        return view('admin.stock-counts.index', [
            'phieu' => StockCount::query()
                ->with('items')
                ->when($trangThai, fn ($q) => $q->where('status', $trangThai))
                ->latest('counted_at')
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'trangThai' => $trangThai,
            'cacTrangThai' => StockCountStatus::cases(),
        ]);
    }

    public function create(StockUnits $donVi): View
    {
        return view('admin.stock-counts.create', [
            'donVi' => $donVi->danhSach(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'counted_at' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
            'dem' => ['required', 'array', 'max:5000'],
            'dem.*.counted' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'dem.*.reason' => ['nullable', 'string', 'max:255'],
        ], [
            'counted_at.before_or_equal' => 'Ngày kiểm kê không thể ở tương lai.',
        ], [
            'counted_at' => 'ngày kiểm kê',
            'dem.*.counted' => 'số đếm được',
        ]);

        try {
            $phieu = $this->service->lap($data['dem'], $data['counted_at'], $data['note'] ?? null);
        } catch (InventoryException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.stock-counts.show', $phieu)
            ->with('success', 'Đã lập phiếu ' . $phieu->code . '. Xem lại chênh lệch rồi bấm "Ghi sổ" để điều chỉnh kho.');
    }

    public function show(StockCount $stockCount): View
    {
        return view('admin.stock-counts.show', [
            'phieu' => $stockCount->load('items.product', 'items.variant', 'createdBy'),
        ]);
    }

    public function post(StockCount $stockCount): RedirectResponse
    {
        try {
            $this->service->ghiSo($stockCount);
        } catch (InventoryException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã ghi sổ phiếu ' . $stockCount->code . '. Tồn kho đã được điều chỉnh theo chênh lệch.');
    }

    public function destroy(StockCount $stockCount): RedirectResponse
    {
        if ($stockCount->isPosted()) {
            return back()->with('error', 'Phiếu đã ghi sổ nên không xoá được. Đếm nhầm thì lập phiếu kiểm kê mới.');
        }

        $ma = $stockCount->code;
        $stockCount->delete();

        return redirect()->route('admin.stock-counts.index')->with('success', 'Đã xoá phiếu nháp ' . $ma . '.');
    }
}
