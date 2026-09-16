<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Support\Collection;

/** Mọi ĐƠN VỊ KHO có theo dõi tồn: (sản phẩm, quy cách) — cho các biểu mẫu chứng từ kho (phiếu nhập, kiểm kê). */
class StockUnits
{
    public function danhSach(bool $boQuaHoa = false): Collection
    {
        return Product::query()
            ->with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->when($boQuaHoa, fn ($q) => $q->where('product_type', '!=', \App\Enums\ProductType::Flower->value))
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
