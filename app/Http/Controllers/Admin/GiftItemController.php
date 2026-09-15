<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GiftKind;
use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Http\Controllers\Controller;
use App\Models\GiftItem;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Vật phẩm dùng làm quà. Xem migration create_gift_tables.
 *
 * XOÁ chỉ khi chưa chương trình nào dùng và chưa đơn nào tặng — đơn cũ
 * phải còn kể được đã tặng gì. Ngừng dùng thì bỏ tích "đang dùng".
 */
class GiftItemController extends Controller
{
    use LogsAdminActivity;

    public function index(): View
    {
        return view('admin.gift-items.index', [
            'vatPham' => GiftItem::query()->with(['product:id,name', 'variant:id,name'])->withCount('campaigns')->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return $this->form(new GiftItem(['is_active' => true, 'kind' => GiftKind::QuaTang]));
    }

    public function edit(GiftItem $giftItem): View
    {
        return $this->form($giftItem);
    }

    public function store(Request $request): RedirectResponse
    {
        $vat = GiftItem::create($this->duLieu($request));
        $this->audit()->log('gift-item.created', 'Thêm vật phẩm quà: ' . $vat->name, $vat);

        return redirect()->route('admin.gift-items.index')->with('success', 'Đã thêm vật phẩm quà.');
    }

    public function update(Request $request, GiftItem $giftItem): RedirectResponse
    {
        $giftItem->update($this->duLieu($request));
        $this->audit()->log('gift-item.updated', 'Sửa vật phẩm quà: ' . $giftItem->name, $giftItem);

        return redirect()->route('admin.gift-items.index')->with('success', 'Đã lưu vật phẩm quà.');
    }

    public function destroy(GiftItem $giftItem): RedirectResponse
    {
        if ($giftItem->campaigns()->exists() || OrderItem::where('gift_item_id', $giftItem->id)->exists()) {
            return back()->with('error', 'Vật phẩm đã được chương trình hoặc đơn hàng dùng — bỏ tích "đang dùng" thay vì xoá.');
        }

        $this->audit()->log('gift-item.deleted', 'Xoá vật phẩm quà: ' . $giftItem->name, $giftItem);
        $giftItem->delete();

        return redirect()->route('admin.gift-items.index')->with('success', 'Đã xoá vật phẩm quà.');
    }

    private function form(GiftItem $vat): View
    {
        return view('admin.gift-items.form', [
            'vat' => $vat,
            'sanPham' => Product::query()->orderBy('name')->get(['id', 'name']),
            'quyCach' => ProductVariant::query()->orderBy('name')->get(['id', 'product_id', 'name']),
        ]);
    }

    /** @return array<string, mixed> */
    private function duLieu(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::enum(GiftKind::class)],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ], [], [
            'name' => 'tên quà',
            'kind' => 'loại quà',
            'product_id' => 'sản phẩm',
            'product_variant_id' => 'quy cách',
            'stock_quantity' => 'số lượng quà',
            'value' => 'trị giá',
        ]);

        $data['name'] = trim($data['name']);
        $data['is_active'] = $request->boolean('is_active');

        if (! empty($data['product_id'])) {
            /*
             * TRỎ SẢN PHẨM: tồn kho là của sản phẩm. Ô số lượng riêng bỏ đi —
             * để lại là có hai con số tồn cho cùng một món.
             */
            $data['stock_quantity'] = null;

            if (! empty($data['product_variant_id'])
                && ! ProductVariant::whereKey($data['product_variant_id'])->where('product_id', $data['product_id'])->exists()) {
                throw ValidationException::withMessages(['product_variant_id' => 'Quy cách không thuộc sản phẩm đã chọn.']);
            }
        } else {
            $data['product_id'] = null;
            $data['product_variant_id'] = null;

            if (($data['stock_quantity'] ?? null) === null) {
                throw ValidationException::withMessages([
                    'stock_quantity' => 'Quà không phải sản phẩm đang bán thì phải nhập số lượng đang có — quà không đếm được là quà vô hạn.',
                ]);
            }
        }

        return $data;
    }
}
