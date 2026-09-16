<?php

namespace App\Services\Pricing;

use App\Enums\OrderStatus;
use App\Enums\UserEventType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\UserEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Số liệu nhu cầu THẬT của từng sản phẩm trong một khoảng thời gian.
 * ⚠️ KHÔNG ĐỌC BẢNG NHẬT KÝ CÁ NHÂN. Lớp này chỉ đọc `user_events`,
 */
class DemandSignals
{
    public function __construct(
        private readonly int $days = 30,
    ) {
    }

    public function days(): int
    {
        return $this->days;
    }

    public function since(): Carbon
    {
        return now()->subDays($this->days)->startOfDay();
    }

    public function all(): Collection
    {
        $tu = $this->since();

        $products = Product::query()
            ->with(['category:id,name', 'promotions'])
            ->where('status', 'active')
            ->get([
                'id', 'category_id', 'name', 'slug', 'base_price',
                'track_inventory', 'stock_quantity', 'created_at',
            ]);

        if ($products->isEmpty()) {
            return collect();
        }

        $ids = $products->pluck('id')->all();

        $luotXem = $this->demSuKien($ids, UserEventType::ProductView, $tu);
        $themGio = $this->demSuKien($ids, UserEventType::AddToCart, $tu);
        $banRa = $this->banRa($ids, $tu);
        $banGanNhat = $this->banGanNhat($ids);

        return $products->map(fn (Product $p) => new ProductDemand(
            product: $p,
            views: (int) ($luotXem[$p->id] ?? 0),
            addToCarts: (int) ($themGio[$p->id] ?? 0),
            unitsSold: (int) ($banRa[$p->id]['qty'] ?? 0),
            ordersWith: (int) ($banRa[$p->id]['orders'] ?? 0),
            lastSoldAt: ($banGanNhat[$p->id] ?? null) ? Carbon::parse($banGanNhat[$p->id]) : null,
            windowDays: $this->days,
        ))->keyBy(fn (ProductDemand $d) => $d->product->id);
    }

    private function demSuKien(array $ids, UserEventType $loai, Carbon $tu): array
    {
        return UserEvent::query()
            ->whereIn('product_id', $ids)
            ->where('event_type', $loai->value)
            ->where('created_at', '>=', $tu)
            ->selectRaw('product_id, count(*) as c')
            ->groupBy('product_id')
            ->pluck('c', 'product_id')
            ->all();
    }

    private function banRa(array $ids, Carbon $tu): array
    {
        $rows = OrderItem::query()
            ->hangBan()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.product_id', $ids)
            ->whereNull('orders.deleted_at')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->where('orders.created_at', '>=', $tu)
            ->selectRaw(
                'order_items.product_id,'
                .' SUM(order_items.quantity) as qty,'
                .' COUNT(DISTINCT order_items.order_id) as orders'
            )
            ->groupBy('order_items.product_id')
            ->get();

        $ket = [];

        foreach ($rows as $r) {
            $ket[(int) $r->product_id] = ['qty' => (int) $r->qty, 'orders' => (int) $r->orders];
        }

        return $ket;
    }

    private function banGanNhat(array $ids): array
    {
        return OrderItem::query()
            ->hangBan()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.product_id', $ids)
            ->whereNull('orders.deleted_at')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->selectRaw('order_items.product_id, MAX(orders.created_at) as last_at')
            ->groupBy('order_items.product_id')
            ->pluck('last_at', 'product_id')
            ->all();
    }

    public function totalOrders(): int
    {
        return Order::query()
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->where('created_at', '>=', $this->since())
            ->count();
    }
}
