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

/**
 * Khai tồn đầu kỳ: hàng đã nằm trên kệ trước khi có hệ thống.
 * ============================================================
 * VÌ SAO LÀ MỘT TRANG RIÊNG, không phải "một phiếu nhập như mọi phiếu".
 *
 * Việc này làm ĐÚNG MỘT LẦN, cho hàng chục mặt hàng cùng lúc, và câu hỏi
 * ở mỗi dòng là "cái này hồi đó mua bao nhiêu" — không phải "nhập thêm
 * bao nhiêu". Bắt người ta lập một phiếu nhập bình thường rồi tự nhớ
 * chọn đúng loại, tự gõ lại số tồn đang có của từng món, là cách chắc
 * chắn nhất để có một phiếu sai.
 *
 * Trang này tự liệt kê những món ĐANG CÓ TỒN MÀ CHƯA CÓ GIÁ VỐN, điền
 * sẵn số lượng đúng bằng tồn hiện tại, và chỉ hỏi một câu cho mỗi dòng.
 *
 * ============================================================
 * KHÔNG ÉP KHAI ĐỦ.
 *
 * Có món thật sự không nhớ nổi giá vốn. Để trống thì món đó vẫn nằm
 * ngoài phần tính lãi — và trang Lãi gộp đã đếm và nói ra phần nằm
 * ngoài. Bịa một con số cho đủ còn tệ hơn: nó biến "chưa biết" thành
 * "biết sai", và không ai phân biệt được nữa.
 */
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

            /*
             * GIÁ VỐN ĐỂ TRỐNG ĐƯỢC, nhưng đã điền thì phải > 0.
             *
             * 0 đồng là một khẳng định ("nhận không mất tiền"), không
             * phải "chưa biết" — xem QĐ-214. Muốn nói chưa biết thì để
             * trống.
             */
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

            // `kind` cố ý không nằm trong $fillable: loại phiếu quyết định
            // việc có cộng vào kho hay không, không phải một ô biểu mẫu.
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

    /**
     * Mặt hàng đang có tồn mà chưa từng có giá vốn nào.
     *
     * Đã khai rồi thì không hỏi lại: hỏi lại là mời người ta khai lần
     * hai, và hai phiếu tồn đầu kỳ cho cùng một món sẽ kéo giá vốn bình
     * quân đi lệch mà không ai thấy.
     */
    private function chuaCoGiaVon(): \Illuminate\Support\Collection
    {
        return Product::query()
            ->where('track_inventory', true)
            ->where('stock_quantity', '>', 0)

            /*
             * BỎ HOA TƯƠI. Đây cũng là chứng từ khai TIỀN, nên nó nằm
             * cùng phía ranh giới với phiếu nhập: giá vốn hoa đến từ lô.
             *
             * Đo được lúc làm: 9 sản phẩm hoa đang bật theo dõi tồn, nên
             * không có dòng này thì chúng hiện ngay ở trang khai và giá
             * vốn hoa bị đếm hai lần.
             */
            ->where('product_type', '!=', \App\Enums\ProductType::Flower->value)
            ->whereNotIn('id', StockReceiptItem::query()
                ->whereNotNull('product_id')
                ->whereNotNull('unit_cost')
                ->select('product_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'product_code', 'stock_quantity', 'base_price']);
    }

    /**
     * @param  array<int|string, array{quantity?: mixed, unit_cost?: mixed}>  $items
     * @return list<array<string, mixed>>
     */
    private function dongHopLe(array $items): array
    {
        $ket = [];

        $sanPham = Product::whereIn('id', array_map('intval', array_keys($items)))
            ->get(['id', 'name', 'product_code'])
            ->keyBy('id');

        foreach ($items as $id => $dong) {
            $soLuong = (int) ($dong['quantity'] ?? 0);
            $gia = $dong['unit_cost'] ?? null;

            /*
             * CHỈ NHẬN DÒNG CÓ ĐỦ CẢ HAI.
             *
             * Số lượng mà không có giá thì dòng đó chẳng khai được gì —
             * nó chỉ làm phiếu dài ra. Giá mà không có số lượng thì không
             * biết giá đó áp cho bao nhiêu cái.
             */
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
