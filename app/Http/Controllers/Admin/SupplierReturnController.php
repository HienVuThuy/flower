<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReturnReason;
use App\Enums\ReturnSettlement;
use App\Enums\StockReceiptKind;
use App\Enums\StockReceiptStatus;
use App\Http\Controllers\Controller;
use App\Models\FlowerLot;
use App\Models\StockReceipt;
use App\Services\Inventory\FlowerLotException;
use App\Services\Inventory\StockReceiptException;
use App\Services\Inventory\SupplierReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Trả hàng cho nhà cung cấp. */
class SupplierReturnController extends Controller
{
    public function __construct(
        private readonly SupplierReturnService $service,
    ) {
    }

    public function index(): View
    {
        $phieuNhap = StockReceipt::query()
            ->where('kind', StockReceiptKind::NhapMoi->value)
            ->where('status', StockReceiptStatus::Posted->value)
            ->with(['items', 'supplier'])
            ->latest('received_at')
            ->limit(30)
            ->get();

        return view('admin.supplier-returns.index', [
            'phieuNhap' => $phieuNhap,

            'loHoa' => FlowerLot::query()
                ->whereNull('tra_lai_qty')
                ->with(['kind', 'supplier'])
                ->latest('purchased_at')
                ->limit(30)
                ->get(),

            'daTra' => StockReceipt::query()
                ->where('kind', StockReceiptKind::TraNcc->value)
                ->with(['items', 'phieuGoc'])
                ->latest('id')
                ->limit(20)
                ->get(),

            'loDaTra' => FlowerLot::query()
                ->whereNotNull('tra_lai_qty')
                ->with('kind')
                ->latest('tra_lai_at')
                ->limit(20)
                ->get(),

            'lyDo' => ReturnReason::cases(),
            'cachXuLy' => ReturnSettlement::cases(),
        ] + $this->daTraTheoDong($phieuNhap));
    }

    private function daTraTheoDong(\Illuminate\Support\Collection $phieuNhap): array
    {
        $tong = \App\Models\StockReceiptItem::query()
            ->join('stock_receipts', 'stock_receipts.id', '=', 'stock_receipt_items.stock_receipt_id')
            ->where('stock_receipts.kind', StockReceiptKind::TraNcc->value)
            ->whereIn('stock_receipts.return_of_id', $phieuNhap->pluck('id'))
            ->selectRaw('stock_receipts.return_of_id as goc, stock_receipt_items.product_id as sp, stock_receipt_items.product_variant_id as qc, sum(stock_receipt_items.quantity) as sl')
            ->groupBy('goc', 'sp', 'qc')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->goc . '|' . $r->sp . '|' . ($r->qc ?? '') => (int) abs((float) $r->sl)]);

        $theoDong = [];

        foreach ($phieuNhap as $p) {
            foreach ($p->items as $dong) {
                $theoDong[$dong->id] = $tong[$p->id . '|' . $dong->product_id . '|' . ($dong->product_variant_id ?? '')] ?? 0;
            }
        }

        return ['daTraTheoDong' => fn (int $id) => $theoDong[$id] ?? 0];
    }

    public function storeGoods(Request $request, StockReceipt $stockReceipt): RedirectResponse
    {
        $data = $request->validate([
            'returned_at' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['required', Rule::enum(ReturnReason::class)],
            'settlement' => ['required', Rule::enum(ReturnSettlement::class)],
            'settlement_amount' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'max:100'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], [], [
            'returned_at' => 'ngày trả',
            'reason' => 'lý do trả',
            'settlement' => 'cách xử lý tiền',
        ]);

        try {
            $phieu = $this->service->traHangDem($stockReceipt, (array) $data['items'], $data);
        } catch (StockReceiptException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.stock-receipts.show', $phieu)
            ->with('success', sprintf(
                'Đã lập phiếu trả %s. Kiểm lại rồi bấm "Ghi sổ" — lúc đó hàng mới trừ khỏi kho và khỏi nền giá vốn.',
                $phieu->code,
            ));
    }

    public function storeFlower(Request $request, FlowerLot $flowerLot): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'reason' => ['required', Rule::enum(ReturnReason::class)],
            'settlement' => ['required', Rule::enum(ReturnSettlement::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], [
            'quantity' => 'số lượng trả',
            'reason' => 'lý do trả',
            'settlement' => 'cách xử lý tiền',
        ]);

        try {
            $this->service->traHangHoa($flowerLot, $data);
        } catch (FlowerLotException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã ghi trả hàng cho lô ' . $flowerLot->code . '.');
    }
}
