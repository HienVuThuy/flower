<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GiftKind;
use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Http\Controllers\Controller;
use App\Models\GiftItem;
use App\Models\Product;
use App\Models\ProductGift;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * QUÀ TẶNG KÈM SẢN PHẨM — chọn sản phẩm, gắn quà mặc định, sửa hoặc bỏ.
 * ============================================================
 * Như "Mua 1 mặt hàng – nhận quà miễn phí" trên các sàn. Thao tác theo
 * SẢN PHẨM chứ không theo "chương trình": người bán nghĩ "sen đá tặng kèm
 * túi phân bón", không nghĩ "tạo chương trình, chọn điều kiện, chọn quà".
 *
 * Quà lấy từ ba nguồn ngay trong một biểu mẫu:
 *   - một sản phẩm đang có trong cửa hàng (dùng chung tồn kho);
 *   - một vật phẩm tặng riêng đã tạo trước đó;
 *   - tạo vật phẩm tặng riêng mới tại chỗ (tên, loại, số lượng đang có).
 */
class ProductGiftController extends Controller
{
    use LogsAdminActivity;

    public function index(Request $request): View
    {
        $coQua = ProductGift::query()->select('product_id');

        $sanPham = Product::query()
            ->whereIn('id', $coQua)
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%' . trim((string) $request->query('q')) . '%'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.product-gifts.index', [
            'sanPham' => $sanPham,
            'quaTheoSanPham' => ProductGift::query()
                ->whereIn('product_id', $sanPham->pluck('id'))
                ->with(['giftItem.product', 'giftItem.variant'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->groupBy('product_id'),
            'chuaCoQua' => Product::query()->whereNotIn('id', $coQua)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Ô "chọn sản phẩm" ở trang danh sách gửi về đây rồi mở trang quà của sản phẩm. */
    public function open(Request $request): RedirectResponse
    {
        $data = $request->validate(['product_id' => ['required', 'integer', 'exists:products,id']], [], ['product_id' => 'sản phẩm']);

        return redirect()->route('admin.product-gifts.edit', $data['product_id']);
    }

    public function edit(Product $product): View
    {
        return view('admin.product-gifts.edit', [
            'product' => $product,
            'cacQua' => ProductGift::query()
                ->where('product_id', $product->id)
                ->with(['giftItem.product', 'giftItem.variant'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'sanPham' => Product::query()->orderBy('name')->get(['id', 'name']),
            'quyCach' => ProductVariant::query()->orderBy('name')->get(['id', 'product_id', 'name']),
            'vatPhamRieng' => GiftItem::query()->whereNull('product_id')->orderBy('name')->get(['id', 'name', 'stock_quantity']),
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'nguon' => ['required', Rule::in(['san_pham', 'vat_pham_co', 'vat_pham_moi'])],
            'gift_product_id' => ['required_if:nguon,san_pham', 'nullable', 'integer', 'exists:products,id'],
            'gift_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'gift_item_id' => ['required_if:nguon,vat_pham_co', 'nullable', 'integer', Rule::exists('gift_items', 'id')->whereNull('product_id')],
            'name' => ['required_if:nguon,vat_pham_moi', 'nullable', 'string', 'max:150'],
            'kind' => ['required_if:nguon,vat_pham_moi', 'nullable', Rule::enum(GiftKind::class)],
            'stock_quantity' => ['required_if:nguon,vat_pham_moi', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'per_quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'gift_quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ], [
            'gift_product_id.required_if' => 'Chọn sản phẩm dùng làm quà.',
            'gift_item_id.required_if' => 'Chọn vật phẩm quà đã có.',
            'name.required_if' => 'Nhập tên vật phẩm quà.',
            'stock_quantity.required_if' => 'Nhập số lượng quà đang có — quà không đếm được là quà vô hạn.',
        ], [
            'per_quantity' => 'mua mỗi',
            'gift_quantity' => 'số quà',
            'stock_quantity' => 'số lượng đang có',
        ]);

        $vat = match ($data['nguon']) {
            'san_pham' => $this->vatPhamTuSanPham((int) $data['gift_product_id'], $data['gift_variant_id'] ?? null, $data['value'] ?? null),
            'vat_pham_co' => GiftItem::whereNull('product_id')->findOrFail($data['gift_item_id']),
            'vat_pham_moi' => GiftItem::create([
                'name' => trim($data['name']),
                'kind' => $data['kind'],
                'stock_quantity' => (int) $data['stock_quantity'],
                'value' => $data['value'] ?? null,
                'is_active' => true,
            ]),
        };

        $qua = ProductGift::firstOrNew(['product_id' => $product->id, 'gift_item_id' => $vat->id]);
        $daCo = $qua->exists;
        $qua->fill([
            'per_quantity' => $data['per_quantity'],
            'gift_quantity' => $data['gift_quantity'],
            'is_active' => true,
        ]);
        $qua->product_id = $product->id;
        $qua->gift_item_id = $vat->id;
        $qua->save();

        $this->audit()->log('product-gift.saved', 'Quà kèm "' . $vat->name . '" cho sản phẩm ' . $product->name, $product, [
            'per_quantity' => $qua->per_quantity,
            'gift_quantity' => $qua->gift_quantity,
        ]);

        return redirect()
            ->route('admin.product-gifts.edit', $product)
            ->with('success', $daCo ? 'Quà này đã gắn sẵn — đã cập nhật số lượng.' : 'Đã thêm quà cho sản phẩm.');
    }

    public function update(Request $request, Product $product, ProductGift $productGift): RedirectResponse
    {
        abort_unless((int) $productGift->product_id === (int) $product->id, 404);

        $data = $request->validate([
            'per_quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'gift_quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ], [], [
            'per_quantity' => 'mua mỗi',
            'gift_quantity' => 'số quà',
            'stock_quantity' => 'số lượng đang có',
        ]);

        $productGift->update([
            'per_quantity' => $data['per_quantity'],
            'gift_quantity' => $data['gift_quantity'],
            'is_active' => $request->boolean('is_active'),
        ]);

        // Tồn kho chỉ sửa ở đây với vật phẩm tặng riêng; quà là sản phẩm thì sửa ở kho của sản phẩm.
        $vat = $productGift->giftItem;
        if ($vat !== null && ! $vat->laSanPham() && array_key_exists('stock_quantity', $data) && $data['stock_quantity'] !== null) {
            $vat->update(['stock_quantity' => (int) $data['stock_quantity']]);
        }

        $this->audit()->log('product-gift.updated', 'Sửa quà kèm "' . $vat?->name . '" của ' . $product->name, $product);

        return redirect()->route('admin.product-gifts.edit', $product)->with('success', 'Đã lưu quà.');
    }

    public function destroy(Product $product, ProductGift $productGift): RedirectResponse
    {
        abort_unless((int) $productGift->product_id === (int) $product->id, 404);

        $this->audit()->log('product-gift.deleted', 'Bỏ quà kèm "' . $productGift->giftItem?->name . '" khỏi ' . $product->name, $product);

        // Đơn cũ vẫn giữ dòng quà (product_gift_id về NULL) — bỏ quà không viết lại lịch sử.
        $productGift->delete();

        return redirect()->route('admin.product-gifts.edit', $product)->with('success', 'Đã bỏ quà khỏi sản phẩm.');
    }

    /** Quà là một sản phẩm đang có: dùng lại vật phẩm trỏ sản phẩm đó nếu đã có. */
    private function vatPhamTuSanPham(int $productId, mixed $variantId, mixed $giaTri): GiftItem
    {
        $variantId = $variantId === null || $variantId === '' ? null : (int) $variantId;

        if ($variantId !== null && ! ProductVariant::whereKey($variantId)->where('product_id', $productId)->exists()) {
            throw ValidationException::withMessages(['gift_variant_id' => 'Quy cách không thuộc sản phẩm đã chọn.']);
        }

        $sp = Product::findOrFail($productId);
        $qc = $variantId ? ProductVariant::find($variantId) : null;

        $vat = GiftItem::firstOrNew(['product_id' => $productId, 'product_variant_id' => $variantId]);

        if (! $vat->exists) {
            $vat->fill([
                'name' => $sp->name . ($qc ? ' — ' . $qc->name : ''),
                'kind' => ($sp->product_type?->value ?? null) === 'plant' ? GiftKind::Cay : GiftKind::DoVat,
                'value' => $giaTri,
                'is_active' => true,
            ])->save();
        }

        return $vat;
    }
}
