<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StockReceiptKind;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockReceipt;
use App\Models\StockReceiptItem;
use App\Services\Inventory\StockReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Khai tồn đầu kỳ: hàng đã nằm trên kệ trước khi có hệ thống. */
class OpeningStockController extends Controller
{
    public function __construct(
        private readonly StockReceiptService $service,
    ) {
    }

    public function create(): View
    {
        return view('admin.opening-stock.create', [
            'matHang' => $this->chuaCoGiaVon(),
            'daKhai' => StockReceipt::where('kind', StockReceiptKind::TonDauKy->value)
                ->withCount('items')
                ->latest('id')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'max:500'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],

            'items.*.unit_cost' => ['nullable', 'numeric', 'min:1', 'max:999999999'],
        ], [], [
            'received_at' => 'ngày chốt tồn',
            'items' => 'danh sách mặt hàng',
        ]);

        $dong = $this->dongHopLe((array) $data['items']);

        if ($dong === []) {
            return back()
                ->withInput()
                ->with('error', 'Chưa có dòng nào điền cả số lượng lẫn giá vốn.');
        }

        $phieu = DB::transaction(function () use ($data, $dong) {
            $phieu = StockReceipt::create([
                'code' => $this->service->sinhMa(),
                'note' => $data['note'] ?? 'Khai tồn đầu kỳ khi bắt đầu dùng hệ thống.',
                'received_at' => $data['received_at'],
            ]);

            $phieu->forceFill(['kind' => StockReceiptKind::TonDauKy])->save();

            $this->service->gan($phieu);

            foreach ($dong as $d) {
                $phieu->items()->create($d);
            }

            return $phieu;
        });

        return redirect()
            ->route('admin.stock-receipts.show', $phieu)
            ->with('success', sprintf(
                'Đã lập phiếu tồn đầu kỳ %s với %d mặt hàng. Kiểm lại rồi bấm "Ghi sổ" — phiếu này KHÔNG cộng vào tồn, nó chỉ khai giá vốn.',
                $phieu->code,
                count($dong),
            ));
    }

    private function chuaCoGiaVon(): \Illuminate\Support\Collection
    {
        return Product::query()
            ->where('track_inventory', true)
            ->where('stock_quantity', '>', 0)

            ->where('product_type', '!=', \App\Enums\ProductType::Flower->value)
            ->whereNotIn('id', StockReceiptItem::query()
                ->whereNotNull('product_id')
                ->whereNotNull('unit_cost')
                ->select('product_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'product_code', 'stock_quantity', 'base_price']);
    }

    private function dongHopLe(array $items): array
    {
        $ket = [];

        $sanPham = Product::whereIn('id', array_map('intval', array_keys($items)))
            ->get(['id', 'name', 'product_code'])
            ->keyBy('id');

        foreach ($items as $id => $dong) {
            $soLuong = (int) ($dong['quantity'] ?? 0);
            $gia = $dong['unit_cost'] ?? null;

            if ($soLuong <= 0 || $gia === null || $gia === '') {
                continue;
            }

            $sp = $sanPham->get((int) $id);

            if (! $sp) {
                continue;
            }

            $ket[] = [
                'product_id' => $sp->id,
                'product_variant_id' => null,
                'product_name' => $sp->name,
                'variant_name' => null,
                'quantity' => $soLuong,
                'unit_cost' => $gia,
            ];
        }

        return $ket;
    }
}
