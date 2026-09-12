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

/**
 * Trả hàng cho nhà cung cấp.
 * ============================================================
 * MỘT TRANG CHO CẢ HAI LOẠI HÀNG.
 *
 * Bên dưới là hai cơ chế khác nhau (phiếu số âm cho hàng đếm được, ghi
 * thẳng lên lô cho hoa), nhưng với người đứng ở quầy thì đó là MỘT việc:
 * "hàng này hỏng, trả lại vựa". Bắt họ nhớ hai chỗ khác nhau tuỳ loại
 * hàng là bắt họ học cấu trúc bên trong của phần mềm.
 */
class SupplierReturnController extends Controller
{
    public function __construct(
        private readonly SupplierReturnService $service,
    ) {
    }

    public function index(): View
    {
        return view('admin.supplier-returns.index', [
            /*
             * NGUỒN ĐỂ TRẢ: phiếu nhập ĐÃ GHI SỔ và lô hoa CHƯA trả lần
             * nào. Phiếu còn nháp không hiện — hàng chưa vào kho thì
             * không có gì để trả, sửa phiếu nháp đó thay vì lập phiếu trả.
             */
            'phieuNhap' => StockReceipt::query()
                ->where('kind', StockReceiptKind::NhapMoi->value)
                ->where('status', StockReceiptStatus::Posted->value)
                ->with(['items', 'supplier'])
                ->latest('received_at')
                ->limit(30)
                ->get(),

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
            'daTraTheoDong' => fn (int $id) => $this->service->soDaTra($id),
        ]);
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
