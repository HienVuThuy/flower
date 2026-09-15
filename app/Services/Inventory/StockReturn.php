<?php

namespace App\Services\Inventory;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Cộng lại vào kho số hàng của một dòng đơn.
 * ============================================================
 * MỘT NƠI cho hai đường hàng quay về: đơn bị huỷ (OrderService) và khách
 * trả hàng (RefundService). Hai bản chép tay sẽ lệch nhau ở đúng chỗ khó
 * thấy nhất — một bên quên rằng sản phẩm có quy cách giữ tồn ở quy cách,
 * hoặc quên bỏ qua hàng không theo dõi tồn.
 *
 * `increment()`, không gán đè: giữa lúc đơn được đặt và lúc hàng quay về
 * có thể đã có hàng chục đơn khác và vài phiếu nhập.
 */
class StockReturn
{
    public function congLai(OrderItem $item, int $soLuong): void
    {
        if ($soLuong <= 0) {
            return;
        }

        /*
         * Quy cách giữ tồn riêng — cộng vào sản phẩm là cộng vào một con
         * số không ai đọc, còn quy cách thì vẫn thiếu.
         */
        if ($item->product_variant_id) {
            ProductVariant::whereKey($item->product_variant_id)
                ->where('track_inventory', true)
                ->increment('stock_quantity', $soLuong);

            return;
        }

        if ($item->product_id) {
            Product::whereKey($item->product_id)
                ->where('track_inventory', true)
                ->increment('stock_quantity', $soLuong);

            return;
        }

        // Quà là vật phẩm tặng riêng (không phải sản phẩm): trả về tồn kho của chính vật phẩm.
        if ($item->is_gift && $item->gift_item_id) {
            \App\Models\GiftItem::whereKey($item->gift_item_id)
                ->whereNull('product_id')
                ->increment('stock_quantity', $soLuong);
        }
    }
}
