<?php

namespace App\Services\Catalog;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Analytics\KhoangThoiGian;

/** Số liệu THẬT để khách tự tin mua: đã bán bao nhiêu, còn bao nhiêu, quy cách nào được chọn nhiều. */
class SocialProof
{
    public const TOI_THIEU_PHO_BIEN = 3;

    public function banGanDay(Product $product, int $soNgay = 30): int
    {
        return (int) OrderItem::query()
            ->hangBan()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.product_id', $product->id)
            ->where('orders.status', OrderStatus::Completed->value)
            ->whereNull('orders.deleted_at')
            ->where('orders.created_at', '>=', KhoangThoiGian::nuaDemTruoc($soNgay - 1))
            ->sum('order_items.quantity');
    }

    public function chiCon(Product $product, bool $coQuyCach): ?int
    {
        if (! $product->track_inventory || $coQuyCach || $product->status !== 'active') {
            return null;
        }

        $ton = (int) $product->stock_quantity;

        return $ton >= 1 && $ton <= \App\Services\Shop\ThamSoKinhDoanh::so('kinh_doanh.nguong_chi_con') ? $ton : null;
    }

    public function quyCachBanChay(Product $product, int $soNgay = 90): ?int
    {
        $theoQuyCach = OrderItem::query()
            ->hangBan()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.product_id', $product->id)
            ->whereNotNull('order_items.product_variant_id')
            ->where('orders.status', OrderStatus::Completed->value)
            ->whereNull('orders.deleted_at')
            ->where('orders.created_at', '>=', KhoangThoiGian::nuaDemTruoc($soNgay - 1))
            ->groupBy('order_items.product_variant_id')
            ->selectRaw('order_items.product_variant_id as quy_cach, SUM(order_items.quantity) as sl')
            ->orderByDesc('sl')
            ->limit(2)
            ->get();

        $dau = $theoQuyCach->first();

        if (! $dau || (int) $dau->sl < self::TOI_THIEU_PHO_BIEN) {
            return null;
        }

        $nhi = $theoQuyCach->get(1);

        if ($nhi && (int) $nhi->sl === (int) $dau->sl) {
            return null;
        }

        return (int) $dau->quy_cach;
    }
}
