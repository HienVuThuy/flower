<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GiftKind;
use App\Enums\GiftReturnRule;
use App\Enums\GiftStockRule;
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

/** QUÀ TẶNG KÈM SẢN PHẨM — chọn sản phẩm, gắn quà mặc định, sửa hoặc bỏ. */
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
                ->with(['giftItem.product', 'giftItem.variant', 'variant'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->groupBy('product_id'),
            'chuaCoQua' => Product::query()->whereNotIn('id', $coQua)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $data = $request->validate(['product_id' => ['required', 'integer', 'exists:products,id']], [], ['product_id' => 'sản phẩm']);

        return redirect()->route('admin.product-gifts.edit', $data['product_id']);
    }

    public function edit(Product $product): View
    {
        return view('admin.product-gifts.edit', [
            'product' => $product,
            'quyCachSanPham' => ProductVariant::query()->where('product_id', $product->id)->orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
            'cacQua' => ProductGift::query()
                ->where('product_id', $product->id)
                ->with(['giftItem.product', 'giftItem.variant', 'variant'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'sanPham' => Product::query()->orderBy('name')->get(['id', 'name']),
            'quyCach' => ProductVariant::query()->orderBy('product_id')->orderBy('sort_order')->orderBy('id')->get(['id', 'product_id', 'name']),
            'vatPhamRieng' => GiftItem::query()->whereNull('product_id')->orderBy('name')->get(['id', 'name', 'stock_quantity']),
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $nguon = $request->validate([
            'nguon' => ['required', Rule::in(['san_pham', 'vat_pham_co', 'vat_pham_moi'])],
            'gift_product_id' => ['required_if:nguon,san_pham', 'nullable', 'integer', 'exists:products,id'],
            'gift_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'gift_item_id' => ['required_if:nguon,vat_pham_co', 'nullable', 'integer', Rule::exists('gift_items', 'id')->whereNull('product_id')],
            'name' => ['required_if:nguon,vat_pham_moi', 'nullable', 'string', 'max:150'],
            'kind' => ['required_if:nguon,vat_pham_moi', 'nullable', Rule::enum(GiftKind::class)],
            'stock_quantity' => ['required_if:nguon,vat_pham_moi', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ], [
            'gift_product_id.required_if' => 'Chọn sản phẩm dùng làm quà.',
            'gift_item_id.required_if' => 'Chọn vật phẩm quà đã có.',
            'name.required_if' => 'Nhập tên vật phẩm quà.',
            'stock_quantity.required_if' => 'Nhập số lượng quà đang có — quà không đếm được là quà vô hạn.',
        ], [
            'stock_quantity' => 'số lượng đang có',
        ]);

        $luat = $this->luat($request, $product);

        $vat = match ($nguon['nguon']) {
            'san_pham' => $this->vatPhamTuSanPham((int) $nguon['gift_product_id'], $nguon['gift_variant_id'] ?? null, $nguon['value'] ?? null),
            'vat_pham_co' => GiftItem::whereNull('product_id')->findOrFail($nguon['gift_item_id']),
            'vat_pham_moi' => GiftItem::create([
                'name' => trim($nguon['name']),
                'kind' => $nguon['kind'],
                'stock_quantity' => (int) $nguon['stock_quantity'],
                'value' => $nguon['value'] ?? null,
                'is_active' => true,
            ]),
        };

        $qua = $this->trung($product, $luat['product_variant_id'], $vat->id) ?? new ProductGift();
        $daCo = $qua->exists;

        $qua->fill($luat + ['is_active' => true]);
        $qua->product_id = $product->id;
        $qua->product_variant_id = $luat['product_variant_id'];
        $qua->gift_item_id = $vat->id;
        $qua->save();

        $this->audit()->log('product-gift.saved', 'Quà kèm "' . $vat->name . '" cho sản phẩm ' . $product->name, $product, [
            'luat' => $qua->moTaLuat(),
        ]);

        return redirect()
            ->route('admin.product-gifts.edit', $product)
            ->with('success', $daCo ? 'Quà này đã gắn sẵn cho đúng quy cách đó — đã cập nhật luật.' : 'Đã thêm quà cho sản phẩm.');
    }

    public function update(Request $request, Product $product, ProductGift $productGift): RedirectResponse
    {
        abort_unless((int) $productGift->product_id === (int) $product->id, 404);

        $luat = $this->luat($request, $product);

        $trung = $this->trung($product, $luat['product_variant_id'], (int) $productGift->gift_item_id);

        if ($trung !== null && $trung->id !== $productGift->id) {
            throw ValidationException::withMessages([
                'trigger_variant_id' => 'Quà này đã được gắn cho quy cách đó ở một dòng khác — sửa dòng đó thay vì tạo trùng.',
            ]);
        }

        $stock = $request->validate(['stock_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000']], [], ['stock_quantity' => 'số lượng đang có']);

        $productGift->fill($luat + ['is_active' => $request->boolean('is_active')]);
        $productGift->product_variant_id = $luat['product_variant_id'];
        $productGift->save();

        $vat = $productGift->giftItem;
        if ($vat !== null && ! $vat->laSanPham() && ($stock['stock_quantity'] ?? null) !== null) {
            $vat->update(['stock_quantity' => (int) $stock['stock_quantity']]);
        }

        $this->audit()->log('product-gift.updated', 'Sửa quà kèm "' . $vat?->name . '" của ' . $product->name, $product, [
            'luat' => $productGift->moTaLuat(),
        ]);

        return redirect()->route('admin.product-gifts.edit', $product)->with('success', 'Đã lưu quà.');
    }

    public function destroy(Product $product, ProductGift $productGift): RedirectResponse
    {
        abort_unless((int) $productGift->product_id === (int) $product->id, 404);

        $this->audit()->log('product-gift.deleted', 'Bỏ quà kèm "' . $productGift->giftItem?->name . '" khỏi ' . $product->name, $product);

        $productGift->delete();

        return redirect()->route('admin.product-gifts.edit', $product)->with('success', 'Đã bỏ quà khỏi sản phẩm.');
    }

    private function luat(Request $request, Product $product): array
    {
        $data = $request->validate([
            'trigger_variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $product->id)],
            'per_quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'gift_quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'max_quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'khi_thieu_kho' => ['required', Rule::enum(GiftStockRule::class)],
            'tra_hang' => ['required', Rule::enum(GiftReturnRule::class)],
        ], [
            'trigger_variant_id.exists' => 'Quy cách không thuộc sản phẩm này.',
        ], [
            'per_quantity' => 'mua mỗi',
            'gift_quantity' => 'số quà',
            'max_quantity' => 'tối đa mỗi đơn',
            'khi_thieu_kho' => 'khi quà không đủ',
            'tra_hang' => 'khi khách trả hàng',
        ]);

        return [
            'product_variant_id' => ($data['trigger_variant_id'] ?? null) !== null ? (int) $data['trigger_variant_id'] : null,
            'per_quantity' => (int) $data['per_quantity'],
            'gift_quantity' => (int) $data['gift_quantity'],
            'max_quantity' => ($data['max_quantity'] ?? null) !== null ? (int) $data['max_quantity'] : null,
            'khi_thieu_kho' => $data['khi_thieu_kho'],
            'tra_hang' => $data['tra_hang'],
            'cho_doi_hang' => $request->boolean('cho_doi_hang'),
        ];
    }

    private function trung(Product $product, ?int $variantId, int $giftItemId): ?ProductGift
    {
        return ProductGift::query()
            ->where('product_id', $product->id)
            ->where('gift_item_id', $giftItemId)
            ->when($variantId !== null, fn ($q) => $q->where('product_variant_id', $variantId), fn ($q) => $q->whereNull('product_variant_id'))
            ->first();
    }

    private function vatPhamTuSanPham(int $productId, mixed $variantId, mixed $giaTri): GiftItem
    {
        $variantId = $variantId === null || $variantId === '' ? null : (int) $variantId;

        if ($variantId !== null && ! ProductVariant::whereKey($variantId)->where('product_id', $productId)->exists()) {
            throw ValidationException::withMessages(['gift_variant_id' => 'Quy cách không thuộc sản phẩm đã chọn làm quà.']);
        }

        return GiftItem::tuSanPham(Product::findOrFail($productId), $variantId, $giaTri);
    }
}
