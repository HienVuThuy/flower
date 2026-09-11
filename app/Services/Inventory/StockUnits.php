<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Mọi ĐƠN VỊ KHO có theo dõi tồn: (sản phẩm, quy cách) — cho các biểu mẫu
 * chứng từ kho (phiếu nhập, kiểm kê).
 *
 * Trước đây nằm riêng trong StockReceiptController. Phiếu kiểm kê cần đúng
 * danh sách này; chép sang là hai nơi quyết định "cái gì nhập được vào kho",
 * và một nơi sẽ quên bỏ hàng không theo dõi tồn.
 *
 * CHỈ HÀNG CÓ BẬT THEO DÕI TỒN: bày ra thứ mà lúc ghi sổ sẽ bị từ chối là để
 * người dùng gõ xong cả phiếu rồi mới biết mình chọn sai.
 */
class StockUnits
{
    /**
     * @return Collection<int, array{value: string, label: string, product_id: int, variant_id: ?int,
     *                               ten: string, quy_cach: ?string, ton: int}>
     */
    public function danhSach(): Collection
    {
        return Product::query()
            ->with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get()
            ->flatMap(function (Product $p) {
                if ($p->variants->isNotEmpty()) {
                    return $p->variants
                        ->filter(fn ($v) => $v->track_inventory)
                        ->map(fn ($v) => [
                            'value' => $p->id . ':' . $v->id,
                            'label' => $p->name . ' — ' . $v->name,
                            'product_id' => $p->id,
                            'variant_id' => $v->id,
                            'ten' => $p->name,
                            'quy_cach' => $v->name,
                            'ton' => (int) $v->stock_quantity,
                        ]);
                }

                if (! $p->track_inventory) {
                    return [];
                }

                return [[
                    'value' => $p->id . ':',
                    'label' => $p->name,
                    'product_id' => $p->id,
                    'variant_id' => null,
                    'ten' => $p->name,
                    'quy_cach' => null,
                    'ton' => (int) $p->stock_quantity,
                ]];
            })
            ->values();
    }
}
