<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StockReceiptStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StockReceiptRequest;
use App\Models\Product;
use App\Models\StockReceipt;
use App\Services\Inventory\InventoryException;
use App\Services\Inventory\StockUnits;
use App\Services\Inventory\StockReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Phiếu nhập kho. */
class StockReceiptController extends Controller
{
    public function __construct(
        private readonly StockReceiptService $service,
    ) {
    }

    public function index(Request $request): View
    {
        $trangThai = StockReceiptStatus::tryFrom((string) $request->query('trang-thai', ''));

        return view('admin.stock-receipts.index', [
            'receipts' => StockReceipt::query()
                ->with('items')
                ->when($trangThai, fn ($q) => $q->where('status', $trangThai))
                ->latest('received_at')
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),

            'trangThai' => $trangThai,
            'cacTrangThai' => StockReceiptStatus::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.stock-receipts.create', [
            'ma' => $this->service->sinhMa(),

            'chonSan' => $request->query('mat-hang'),

            'donViKho' => $this->donViKho(),

            'nhaCungCap' => \App\Models\Supplier::dangHoatDong()->orderBy('name')->get(['id', 'name', 'kind']),
        ]);
    }

    public function store(StockReceiptRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $receipt = DB::transaction(function () use ($data) {
            $ncc = isset($data['supplier_id'])
                ? \App\Models\Supplier::find($data['supplier_id'])
                : null;

            $receipt = StockReceipt::create([
                'code' => $this->service->sinhMa(),
                'supplier_id' => $ncc?->id,
                'supplier' => $ncc?->name,
                'note' => $data['note'] ?? null,
                'received_at' => $data['received_at'],
            ]);

            $this->service->gan($receipt);

            foreach ($this->dongHopLe($data['items'] ?? []) as $dong) {
                $receipt->items()->create($dong);
            }

            return $receipt;
        });

        return redirect()
            ->route('admin.stock-receipts.show', $receipt)
            ->with('success', 'Đã tạo phiếu nhập ' . $receipt->code . '. Kiểm lại rồi bấm "Ghi sổ" để cộng vào kho.');
    }

    public function show(StockReceipt $stockReceipt): View
    {
        return view('admin.stock-receipts.show', [
            'receipt' => $stockReceipt->load('items.product', 'createdBy'),
        ]);
    }

    public function post(StockReceipt $stockReceipt): RedirectResponse
    {
        try {
            $this->service->ghiSo($stockReceipt);
        } catch (InventoryException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã ghi sổ phiếu ' . $stockReceipt->code . '. Tồn kho đã được cộng thêm.');
    }

    public function destroy(StockReceipt $stockReceipt): RedirectResponse
    {
        if ($stockReceipt->isPosted()) {
            return back()->with('error',
                'Phiếu đã ghi sổ nên không xoá được. Nhập nhầm thì lập một phiếu điều chỉnh với số lượng âm.');
        }

        $ma = $stockReceipt->code;
        $stockReceipt->delete();

        return redirect()
            ->route('admin.stock-receipts.index')
            ->with('success', 'Đã xoá phiếu nháp ' . $ma . '.');
    }

    private function donViKho()
    {
        return app(StockUnits::class)->danhSach(boQuaHoa: true)
            ->map(fn (array $d) => ['value' => $d['value'], 'label' => $d['label']]);
    }

    private function dongHopLe(array $items): array
    {
        $ket = [];

        foreach ($items as $dong) {
            $soLuong = (int) ($dong['quantity'] ?? 0);

            if ($soLuong === 0 || empty($dong['mat_hang'])) {
                continue;
            }

            [$productId, $variantId] = array_pad(explode(':', (string) $dong['mat_hang'], 2), 2, null);

            $product = Product::find((int) $productId);

            if (! $product) {
                continue;
            }

            if ($product->product_type === \App\Enums\ProductType::Flower) {
                continue;
            }

            $variant = $variantId
                ? $product->variants()->whereKey((int) $variantId)->first()
                : null;

            if ($variantId && ! $variant) {
                continue;
            }

            $gia = $dong['unit_cost'] ?? null;

            $ket[] = [
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'product_name' => $product->name,
                'variant_name' => $variant?->name,
                'quantity' => $soLuong,

                'unit_cost' => ($gia === null || $gia === '') ? null : (float) $gia,
            ];
        }

        return $ket;
    }
}
