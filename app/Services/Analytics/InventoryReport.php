<?php

namespace App\Services\Analytics;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Báo cáo tồn kho.
 * ⚠️ KHÔNG TÍNH ĐƯỢC LỢI NHUẬN, VÀ KHÔNG BỊA RA.
 */
class InventoryReport
{
    private int $ngay = 30;

    public function trongVong(int $ngay): static
    {
        $this->ngay = max(1, $ngay);

        return $this;
    }

    public function rows(): Collection
    {
        $daBan = $this->daBanTrongKy();

        $dong = collect();

        Product::query()
            ->with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->chunk(200, function ($sanPham) use (&$dong, $daBan) {
                foreach ($sanPham as $p) {
                    $quyCach = $p->variants;

                    if ($quyCach->isNotEmpty()) {
                        foreach ($quyCach as $v) {
                            if (! $v->track_inventory) {
                                continue;
                            }

                            $dong->push($this->dungDong(
                                $p,
                                $v->id,
                                $v->name,
                                (int) $v->stock_quantity,
                                (float) ($v->price ?? $p->base_price),
                                $daBan,
                            ));
                        }

                        continue;
                    }

                    if (! $p->track_inventory) {
                        continue;
                    }

                    $dong->push($this->dungDong($p, null, null, (int) $p->stock_quantity, (float) $p->base_price, $daBan));
                }
            });

        return $dong->values();
    }

    private function dungDong(Product $p, ?int $variantId, ?string $tenQuyCach, int $ton, float $gia, Collection $daBan): array
    {
        $ban = (int) ($daBan[$this->khoa($p->id, $variantId)] ?? 0);
        $moiNgay = $ban / $this->ngay;

        return [
            'product' => $p,
            'variant_id' => $variantId,
            'name' => $p->name,
            'variant' => $tenQuyCach,
            'stock' => $ton,
            'sold' => $ban,
            'per_day' => $moiNgay,

            'cover' => $moiNgay > 0 ? $ton / $moiNgay : null,

            'price' => $gia,
            'value' => $ton * $gia,

            'selling' => $p->status === 'active',
        ];
    }

    private function daBanTrongKy(): Collection
    {
        return OrderItem::query()
            ->hangBan()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', OrderStatus::Completed->value)
            ->whereNull('orders.deleted_at')
            ->where('orders.created_at', '>=', now()->subDays($this->ngay))
            ->selectRaw('order_items.product_id, order_items.product_variant_id, SUM(order_items.quantity) as sl')
            ->groupBy('order_items.product_id', 'order_items.product_variant_id')
            ->get()
            ->mapWithKeys(fn ($r) => [$this->khoa((int) $r->product_id, $r->product_variant_id ? (int) $r->product_variant_id : null) => (int) $r->sl]);
    }

    private function khoa(?int $productId, ?int $variantId): string
    {
        return $productId . ':' . ($variantId ?? '0');
    }

    public function sapHet(float $nguong = 14, int $limit = 20): Collection
    {
        return $this->rows()
            ->filter(fn (array $r) => $r['cover'] !== null && $r['cover'] <= $nguong)
            ->sortBy('cover')
            ->take($limit)
            ->values();
    }

    public function daHet(int $limit = 50): Collection
    {
        return $this->rows()
            ->filter(fn (array $r) => $r['stock'] <= 0 && $r['selling'])
            ->sortByDesc('sold')
            ->take($limit)
            ->values();
    }

    public function chetVon(int $limit = 20): Collection
    {
        return $this->rows()
            ->filter(fn (array $r) => $r['stock'] > 0 && $r['sold'] === 0)
            ->sortByDesc('value')
            ->take($limit)
            ->values();
    }

    public function tongQuan(): array
    {
        $rows = $this->rows();

        return [
            'units' => (int) $rows->sum('stock'),
            'skus' => $rows->count(),
            'value' => (float) $rows->sum('value'),
            'out' => $rows->filter(fn ($r) => $r['stock'] <= 0 && $r['selling'])->count(),
            'low' => $rows->filter(fn ($r) => $r['cover'] !== null && $r['cover'] <= 14 && $r['stock'] > 0)->count(),
            'dead' => $rows->filter(fn ($r) => $r['stock'] > 0 && $r['sold'] === 0)->count(),
        ];
    }
}
