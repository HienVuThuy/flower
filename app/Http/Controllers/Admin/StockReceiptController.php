<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StockReceiptStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StockReceiptRequest;
use App\Models\Product;
use App\Models\StockReceipt;
use App\Services\Inventory\StockReceiptException;
use App\Services\Inventory\StockReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Phiếu nhập kho.
 * ============================================================
 * KHÔNG CÓ `update` VÀ `destroy` CHO PHIẾU ĐÃ GHI SỔ.
 *
 * Ghi sổ là một hành động có tác động thật: kho đã cộng thêm. Sửa hay
 * xoá một chứng từ sau khi nó đã tác động là làm sổ sách không còn khớp
 * với thực tế, và không ai lần ra được vì sao lệch.
 *
 * Nhập nhầm thì lập một phiếu điều chỉnh (số lượng âm) — y như cách kế
 * toán làm với hoá đơn đã phát hành.
 */
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

            /*
             * MỞ SẴN ĐÚNG MẶT HÀNG KHI ĐẾN TỪ TRANG TỒN KHO.
             *
             * Nút "Nhập thêm" ở đó biết chính xác món nào đang thiếu.
             * Bắt người dùng đi tìm lại nó trong danh sách 52 mặt hàng là
             * vứt đi thông tin mình vừa có trong tay.
             */
            'chonSan' => $request->query('mat-hang'),

            'donViKho' => $this->donViKho(),
        ]);
    }

    public function store(StockReceiptRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $receipt = DB::transaction(function () use ($data) {
            $receipt = StockReceipt::create([
                'code' => $this->service->sinhMa(),
                'supplier' => $data['supplier'] ?? null,
                'note' => $data['note'] ?? null,
                'received_at' => $data['received_at'],
            ]);

            $this->service->gan($receipt);

            foreach ($this->dongHopLe($data['items'] ?? []) as $dong) {
                $receipt->items()->create($dong);
            }

            return $receipt;
        });

        /*
         * TẠO XONG LÀ NHÁP, chưa cộng vào kho.
         *
         * Người lập phải nhìn lại phiếu rồi mới bấm ghi sổ. Cộng luôn thì
         * một lần gõ nhầm số lượng đi thẳng vào kho, và không có bước nào
         * để phát hiện.
         */
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

    /** Cộng phiếu vào kho. */
    public function post(StockReceipt $stockReceipt): RedirectResponse
    {
        try {
            $this->service->ghiSo($stockReceipt);
        } catch (StockReceiptException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã ghi sổ phiếu ' . $stockReceipt->code . '. Tồn kho đã được cộng thêm.');
    }

    /** Xoá phiếu — CHỈ khi còn là nháp. */
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

    /**
     * Mọi đơn vị kho có thể nhập, cho ô chọn.
     *
     * CHỈ MẶT HÀNG CÓ BẬT THEO DÕI TỒN. Cho chọn thứ không theo dõi tồn
     * là bày ra một lựa chọn mà lúc ghi sổ sẽ bị từ chối — sau khi người
     * dùng đã gõ xong cả phiếu.
     *
     * @return \Illuminate\Support\Collection<int, array{value: string, label: string}>
     */
    private function donViKho()
    {
        return Product::query()
            ->with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get()
            ->flatMap(function (Product $p) {
                $quyCach = $p->variants;

                if ($quyCach->isNotEmpty()) {
                    return $quyCach
                        ->filter(fn ($v) => $v->track_inventory)
                        ->map(fn ($v) => [
                            'value' => $p->id . ':' . $v->id,
                            'label' => $p->name . ' — ' . $v->name,
                        ]);
                }

                if (! $p->track_inventory) {
                    return [];
                }

                return [[
                    'value' => $p->id . ':',
                    'label' => $p->name,
                ]];
            })
            ->values();
    }

    /**
     * Đổi dữ liệu biểu mẫu thành dòng hàng, bỏ dòng trống.
     *
     * KHÔNG TIN `mat_hang` TỪ BIỂU MẪU. Chuỗi "id:idQuyCach" đến từ
     * trình duyệt; phải tra lại trong cơ sở dữ liệu chứ không đưa thẳng
     * vào khoá ngoại. Tên cũng đọc từ bản ghi thật, không nhận từ biểu
     * mẫu — nếu không, phiếu ghi được một cái tên bịa.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function dongHopLe(array $items): array
    {
        $ket = [];

        foreach ($items as $dong) {
            $soLuong = (int) ($dong['quantity'] ?? 0);

            // Dòng để trống là dòng người dùng không dùng tới — bỏ qua
            // im lặng, không báo lỗi.
            if ($soLuong === 0 || empty($dong['mat_hang'])) {
                continue;
            }

            [$productId, $variantId] = array_pad(explode(':', (string) $dong['mat_hang'], 2), 2, null);

            $product = Product::find((int) $productId);

            if (! $product) {
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

                /*
                 * Ô GIÁ ĐỂ TRỐNG THÌ LƯU NULL, không phải 0.
                 *
                 * NULL là "không có số liệu" (hàng tặng, hàng mẫu, chưa
                 * biết giá); 0 là "nhận không mất tiền". Gộp lại thì giá
                 * vốn bình quân bị kéo xuống bởi những lô chưa ai điền.
                 */
                'unit_cost' => ($gia === null || $gia === '') ? null : (float) $gia,
            ];
        }

        return $ket;
    }
}
