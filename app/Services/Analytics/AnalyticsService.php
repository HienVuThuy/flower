<?php

namespace App\Services\Analytics;

use App\Enums\GhnFeePayer;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use App\Enums\ShippingStatus;
use App\Enums\UserEventType;
use App\Models\Order;
use App\Models\Refund;
use App\Models\UserEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Truy vấn cho trang Phân tích của admin. */
class AnalyticsService
{
    public const PERIODS = [
        '7' => '7 ngày qua',
        '30' => '30 ngày qua',
        'all' => 'Toàn bộ',
    ];

    private ?Carbon $since = null;

    private ?Carbon $until = null;

    public function forPeriod(string $period): static
    {
        $this->since = self::startOf($period);
        $this->until = null;

        return $this;
    }

    public function forRange(Carbon $tu, Carbon $den): static
    {
        $this->since = $tu;
        $this->until = $den;

        return $this;
    }

    public function forPreviousPeriod(string $period): bool
    {
        if ($this->since !== null && $this->until !== null) {
            $dai = (int) $this->since->diffInSeconds($this->until);

            $this->until = $this->since->copy();
            $this->since = $this->since->copy()->subSeconds($dai);

            return true;
        }

        $start = self::startOf($period);

        if ($start === null) {
            return false;
        }

        $length = $start->diffInSeconds(now());

        $this->until = $start;
        $this->since = $start->copy()->subSeconds((int) $length);

        return true;
    }

    public static function hopLeKy(mixed $ky, string $macDinh = '30'): string
    {
        $ky = is_scalar($ky) ? (string) $ky : '';

        return array_key_exists($ky, self::PERIODS) ? $ky : $macDinh;
    }

    private static function startOf(string $period): ?Carbon
    {
        return match ($period) {
            '7' => KhoangThoiGian::nuaDemTruoc(7 - 1),
            '30' => KhoangThoiGian::nuaDemTruoc(30 - 1),
            default => null,
        };
    }

    public function khoang(): KhoangThoiGian
    {
        return new KhoangThoiGian($this->since, $this->until);
    }

    public static function change(float $now, float $before): ?float
    {
        if ($before <= 0.0) {
            return null;
        }

        return round(($now - $before) / $before * 100, 1);
    }

    public function funnel(): array
    {
        $views = $this->distinctSessions(UserEventType::ProductView);
        $carts = $this->distinctSessions(UserEventType::AddToCart);
        $purchases = $this->distinctSessions(UserEventType::Purchase);

        return [
            'views' => $views,
            'carts' => $carts,
            'purchases' => $purchases,
            'view_to_cart' => $this->rate($carts, $views),
            'cart_to_purchase' => $this->rate($purchases, $carts),
            'view_to_purchase' => $this->rate($purchases, $views),
        ];
    }

    public function eventTotals(): Collection
    {
        return $this->events()
            ->selectRaw('event_type, COUNT(*) as total')
            ->groupBy('event_type')
            ->pluck('total', 'event_type');
    }

    public function topProducts(UserEventType $type, int $limit = 8): Collection
    {
        $rows = $this->events()
            ->where('event_type', $type)
            ->whereNotNull('product_id')
            ->selectRaw('product_id, COUNT(*) as total')
            ->groupBy('product_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $products = \App\Models\Product::whereIn('id', $rows->pluck('product_id'))
            ->get()
            ->keyBy('id');

        return $rows->map(fn ($r) => [
            'product' => $products->get($r->product_id),
            'total' => (int) $r->total,
        ]);
    }

    public function topCategories(int $limit = 6): Collection
    {
        $rows = $this->events()
            ->whereIn('event_type', [UserEventType::ProductView, UserEventType::CategoryView])
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $categories = \App\Models\Category::whereIn('id', $rows->pluck('category_id'))
            ->get()
            ->keyBy('id');

        return $rows->map(fn ($r) => [
            'category' => $categories->get($r->category_id),
            'total' => (int) $r->total,
        ]);
    }

    public function topSearches(int $limit = 10): Collection
    {
        $terms = $this->events()
            ->where('event_type', UserEventType::Search)
            ->pluck('meta')
            ->map(fn ($meta) => is_array($meta) ? trim((string) ($meta['q'] ?? '')) : '')
            ->filter()
            ->map(fn (string $q) => mb_strtolower($q));

        return $terms->countBy()
            ->sortDesc()
            ->take($limit)
            ->map(fn ($total, $term) => ['term' => $term, 'total' => $total])
            ->values();
    }

    public function dailyActivity(int $days = 14): Collection
    {
        $from = KhoangThoiGian::nuaDemTruoc($days - 1);

        $counts = UserEvent::where('created_at', '>=', $from)
            ->pluck('created_at')
            ->countBy(fn ($t) => KhoangThoiGian::diaPhuong($t)->toDateString());

        $dauNgay = KhoangThoiGian::diaPhuong($from);

        return collect(range(0, $days - 1))->map(function (int $i) use ($dauNgay, $counts) {
            $day = $dauNgay->copy()->addDays($i);
            $key = $day->toDateString();

            return [
                'date' => $key,
                'label' => $day->format('d/m'),
                'total' => (int) ($counts[$key] ?? 0),
            ];
        });
    }

    public function orderStats(): array
    {
        $query = $this->applyWindow(Order::query(), 'created_at');

        $total = (clone $query)->count();
        $completed = (clone $query)->where('status', OrderStatus::Completed)->count();
        $cancelled = (clone $query)->where('status', OrderStatus::Cancelled)->count();
        $revenue = (float) (clone $query)->where('status', OrderStatus::Completed)->sum('grand_total');

        $refunded = (float) Refund::query()
            ->where('status', RefundStatus::Completed->value)
            ->whereIn('order_id', (clone $query)->where('status', OrderStatus::Completed)->select('id'))
            ->sum('amount');

        $buThem = (float) \App\Models\Exchange::query()
            ->where('status', \App\Enums\ExchangeStatus::HoanTat->value)
            ->whereIn('order_id', (clone $query)->where('status', OrderStatus::Completed)->select('id'))
            ->sum('da_thu');

        return [
            'total' => $total,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'revenue' => $revenue,
            'refunded' => $refunded,
            'bu_doi_hang' => $buThem,
            'net_revenue' => $revenue - $refunded + $buThem,
            'average' => $completed > 0 ? $revenue / $completed : null,
        ];
    }

    public function revenueByDay(int $toiDa = 90): Collection
    {
        $mg = KhoangThoiGian::muiGio();

        $tu = $this->since
            ? KhoangThoiGian::diaPhuong($this->since)->startOfDay()
            : now($mg)->subDays($toiDa - 1)->startOfDay();

        $den = $this->until
            ? KhoangThoiGian::diaPhuong($this->until)->subSecond()->endOfDay()
            : now($mg)->endOfDay();

        if ($tu->diffInDays($den) > $toiDa) {
            $tu = $den->copy()->subDays($toiDa - 1)->startOfDay();
        }

        $luu = (string) config('app.timezone');

        $nhom = Order::query()
            ->whereBetween('created_at', [$tu->copy()->setTimezone($luu), $den->copy()->setTimezone($luu)])
            ->where('status', OrderStatus::Completed)
            ->get(['created_at', 'grand_total'])
            ->groupBy(fn ($o) => KhoangThoiGian::diaPhuong($o->created_at)->toDateString());

        $soNgay = (int) $tu->copy()->startOfDay()->diffInDays($den->copy()->startOfDay()) + 1;

        return collect(range(0, $soNgay - 1))->map(function (int $i) use ($tu, $nhom) {
            $ngay = $tu->copy()->addDays($i);
            $key = $ngay->toDateString();
            $dsDon = $nhom->get($key, collect());

            return [
                'date' => $key,
                'label' => $ngay->format('d/m'),
                'revenue' => (float) $dsDon->sum('grand_total'),
                'orders' => $dsDon->count(),
            ];
        });
    }

    public function statusBreakdown(): Collection
    {
        $rows = $this->applyWindow(Order::query(), 'created_at')
            ->selectRaw('status, COUNT(*) as tong, SUM(grand_total) as tien')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return collect(OrderStatus::cases())->map(fn (OrderStatus $tt) => [
            'status' => $tt,
            'total' => (int) ($rows->get($tt->value)->tong ?? 0),
            'revenue' => (float) ($rows->get($tt->value)->tien ?? 0),
        ]);
    }

    public function paymentMix(): Collection
    {
        $dem = $this->applyWindow(Order::query(), 'created_at')
            ->selectRaw('payment_method, COUNT(*) as tong')
            ->groupBy('payment_method')
            ->pluck('tong', 'payment_method');

        $tien = $this->applyWindow(Order::query(), 'created_at')
            ->where('status', OrderStatus::Completed)
            ->selectRaw('payment_method, SUM(grand_total) as tien')
            ->groupBy('payment_method')
            ->pluck('tien', 'payment_method');

        return collect(PaymentMethod::cases())->map(fn (PaymentMethod $ht) => [
            'method' => $ht,
            'total' => (int) ($dem[$ht->value] ?? 0),
            'revenue' => (float) ($tien[$ht->value] ?? 0),
        ]);
    }

    public function topCustomers(int $limit = 8): Collection
    {
        return $this->applyWindow(Order::query(), 'orders.created_at')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->where('orders.status', OrderStatus::Completed->value)
            ->groupBy('users.id', 'users.name', 'users.email')
            ->selectRaw('users.name, users.email, COUNT(*) as so_don, SUM(orders.grand_total) as tien')
            ->orderByDesc('tien')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'name' => (string) $r->name,
                'email' => $r->email,
                'orders' => (int) $r->so_don,
                'revenue' => (float) $r->tien,
            ]);
    }

    public function couponUsage(int $limit = 10): Collection
    {
        return $this->applyWindow(Order::query(), 'created_at')
            ->whereNotNull('coupon_code')
            ->groupBy('coupon_code')
            ->selectRaw('coupon_code, COUNT(*) as so_don, SUM(coupon_discount) as giam')
            ->orderByDesc('giam')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'code' => (string) $r->coupon_code,
                'orders' => (int) $r->so_don,
                'discount' => (float) $r->giam,
            ]);
    }

    public function bestSellers(int $limit = 8): Collection
    {
        $query = \App\Models\OrderItem::query()
            ->hangBan()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', OrderStatus::Completed->value)
            ->whereNull('orders.deleted_at');

        $this->applyWindow($query, 'orders.created_at');

        return $query
            ->selectRaw(
                'order_items.product_id,'
                .' MAX(order_items.product_name) as product_name,'
                .' SUM(order_items.quantity) as qty,'
                .' SUM(order_items.line_total) as revenue'
            )
            ->groupBy('order_items.product_id')
            ->orderByDesc('qty')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'name' => $r->product_name,
                'quantity' => (int) $r->qty,
                'revenue' => (float) $r->revenue,
            ]);
    }

    public function refundList(int $limit = 1000): Collection
    {
        return $this->applyWindow(Refund::query(), 'created_at')
            ->with('order:id,order_number')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function shippingCost(): array
    {
        $vanDon = $this->applyWindow(Order::query(), 'created_at')->whereNotNull('ghn_order_code');

        $nguoiNhanTra = (clone $vanDon)
            ->where(fn ($q) => $q->whereNull('ghn_fee_payer')->orWhere('ghn_fee_payer', '!=', GhnFeePayer::Shop->value))
            ->count();

        $cuaHangTra = (clone $vanDon)->where('ghn_fee_payer', GhnFeePayer::Shop->value);

        $daHuy = (clone $cuaHangTra)->where('shipping_status', ShippingStatus::Cancel->value)->count();

        $thieuCuoc = (clone $cuaHangTra)
            ->where('shipping_status', '!=', ShippingStatus::Cancel->value)
            ->whereNull('ghn_total_fee')
            ->count();

        $dong = $this->vanDonTinhDuocCuoc()->get(['shipping_fee', 'ghn_total_fee', 'shipping_status']);

        [$thu, $tra] = $this->congCuoc($dong);

        return [
            'van_don' => (clone $vanDon)->count(),
            'tinh_duoc' => $dong->count(),
            'thu' => $thu,
            'tra' => $tra,
            'chenh' => bcsub($tra, $thu, 2),
            'mien_phi' => $dong->filter(fn ($o) => bccomp((string) $o->shipping_fee, '0', 2) === 0)->count(),
            'hoan_hang' => $dong->filter(fn ($o) => in_array($o->shipping_status, [
                ShippingStatus::Returned->value,
                ShippingStatus::DeliveryFail->value,
            ], true))->count(),
            'loai' => [
                'nguoi_nhan_tra' => $nguoiNhanTra,
                'da_huy' => $daHuy,
                'thieu_cuoc' => $thieuCuoc,
            ],
        ];
    }

    public function shippingCostByMonth(): Collection
    {
        return $this->vanDonTinhDuocCuoc()
            ->orderBy('created_at')
            ->get(['created_at', 'shipping_fee', 'ghn_total_fee'])
            ->groupBy(fn ($o) => KhoangThoiGian::diaPhuong($o->created_at)->format('Y-m'))
            ->map(function (Collection $nhom, string $thang) {
                [$thu, $tra] = $this->congCuoc($nhom);

                return [
                    'thang' => $thang,
                    'don' => $nhom->count(),
                    'thu' => $thu,
                    'tra' => $tra,
                    'chenh' => bcsub($tra, $thu, 2),
                ];
            })
            ->values();
    }

    public function shippingSubsidies(int $limit = 10): Collection
    {
        return $this->vanDonTinhDuocCuoc()
            ->whereRaw('ghn_total_fee > shipping_fee')
            ->orderByRaw('(ghn_total_fee - shipping_fee) DESC')
            ->limit($limit)
            ->get()
            ->map(fn (Order $o) => [
                'order' => $o,
                'thu' => (string) $o->shipping_fee,
                'tra' => number_format((int) $o->ghn_total_fee, 2, '.', ''),
                'chenh' => bcsub((string) $o->ghn_total_fee, (string) $o->shipping_fee, 2),
            ]);
    }

    private function vanDonTinhDuocCuoc()
    {
        return $this->applyWindow(Order::query(), 'created_at')
            ->whereNotNull('ghn_order_code')
            ->where('ghn_fee_payer', GhnFeePayer::Shop->value)
            ->where('shipping_status', '!=', ShippingStatus::Cancel->value)
            ->whereNotNull('ghn_total_fee');
    }

    private function congCuoc(Collection $dong): array
    {
        $thu = '0.00';
        $tra = '0.00';

        foreach ($dong as $o) {
            $thu = bcadd($thu, (string) $o->shipping_fee, 2);
            $tra = bcadd($tra, (string) $o->ghn_total_fee, 2);
        }

        return [$thu, $tra];
    }

    private function events()
    {
        return $this->applyWindow(UserEvent::query(), 'created_at');
    }

    private function applyWindow(mixed $query, string $column): mixed
    {
        return $this->khoang()->apDung($query, $column);
    }

    private function distinctSessions(UserEventType $type): int
    {
        return (int) $this->events()
            ->where('event_type', $type)
            ->distinct()
            ->count('session_id');
    }

    private function rate(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }
}
