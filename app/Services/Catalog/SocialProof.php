<?php

namespace App\Services\Catalog;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Analytics\KhoangThoiGian;

/**
 * Số liệu THẬT để khách tự tin mua: đã bán bao nhiêu, còn bao nhiêu, quy cách nào được chọn nhiều.
 * ============================================================
 * VÌ SAO GOM MỘT CHỖ: đây là nơi dễ bịa nhất của một trang bán hàng —
 * "1.2k đã bán", "Chỉ còn 2!", "Bán chạy nhất" in cứng cho mọi sản phẩm.
 * Mọi con số ở đây đọc từ đơn và kho thật; không đủ dữ liệu thì trả null
 * hoặc 0, và giao diện KHÔNG in gì.
 *
 * ĐƠN ĐÃ GIAO, không phải đơn đã đặt: đơn huỷ hay đang chờ chưa phải một
 * lần "đã bán". Cùng định nghĩa với báo cáo doanh thu.
 */
class SocialProof
{
    /** Còn từ ngần này trở xuống thì mới nói "chỉ còn". */
    public const NGUONG_CHI_CON = 5;

    /** Quy cách phải bán được ít nhất ngần này để gọi là "phổ biến". */
    public const TOI_THIEU_PHO_BIEN = 3;

    /** Số lượng đã bán trong N ngày qua (tính cả hôm nay, theo giờ Việt Nam). */
    public function banGanDay(Product $product, int $soNgay = 30): int
    {
        return (int) OrderItem::query()
            ->hangBan() // quà tặng không phải "đã bán"
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.product_id', $product->id)
            ->where('orders.status', OrderStatus::Completed->value)
            ->whereNull('orders.deleted_at')
            ->where('orders.created_at', '>=', KhoangThoiGian::nuaDemTruoc($soNgay - 1))
            ->sum('order_items.quantity');
    }

    /**
     * Số còn lại khi đã THẬT SỰ ít — hoặc null.
     *
     * Chỉ nói khi: có quản lý kho (hàng làm theo đơn không có "còn bao
     * nhiêu"), không có quy cách (tồn nằm ở từng quy cách — gộp lại là
     * nói sai với quy cách khách đang chọn), và còn từ 1 tới ngưỡng.
     */
    public function chiCon(Product $product, bool $coQuyCach): ?int
    {
        if (! $product->track_inventory || $coQuyCach || $product->status !== 'active') {
            return null;
        }

        $ton = (int) $product->stock_quantity;

        return $ton >= 1 && $ton <= self::NGUONG_CHI_CON ? $ton : null;
    }

    /**
     * Quy cách được mua nhiều nhất trong N ngày — hoặc null khi chưa đủ căn cứ.
     *
     * Null khi: tổng bán dưới ngưỡng (hai ba đơn chưa nói lên "phổ biến"),
     * hoặc hai quy cách đứng đầu bằng nhau (gắn nhãn cho một trong hai là
     * dựng ra khác biệt không có thật).
     */
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
