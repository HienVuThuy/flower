<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductVariant;

/** NƠI DUY NHẤT cộng hoặc trừ tồn kho theo chứng từ (phiếu nhập, kiểm kê). */
class StockAdjuster
{
    public function dieuChinh(?int $variantId, ?int $productId, int $soLuong, string $ten, bool $khongDuocAm = false): void
    {
        if ($soLuong === 0) {
            return;
        }

        $dong = $variantId !== null
            ? $this->khoaQuyCach($variantId, $ten)
            : $this->khoaSanPham($productId, $ten);

        if ($khongDuocAm && (int) $dong->stock_quantity + $soLuong < 0) {
            throw new InventoryException(sprintf(
                '"%s": tồn hiện tại %d, điều chỉnh %+d sẽ thành số âm. Có thể đã có đơn bán giữa lúc đếm và lúc ghi sổ — hãy lập lại phiếu.',
                $ten,
                (int) $dong->stock_quantity,
                $soLuong,
            ));
        }

        $dong->increment('stock_quantity', $soLuong);
    }

    private function khoaQuyCach(int $variantId, string $ten): ProductVariant
    {
        $so = ProductVariant::whereKey($variantId)->lockForUpdate()->first();

        if (! $so) {
            throw new InventoryException(sprintf('Quy cách của "%s" không còn tồn tại.', $ten));
        }

        if (! $so->track_inventory) {
            throw new InventoryException(sprintf(
                'Quy cách "%s" không bật theo dõi tồn kho nên không điều chỉnh kho được.',
                $so->name,
            ));
        }

        return $so;
    }

    private function khoaSanPham(?int $productId, string $ten): Product
    {
        $sp = Product::whereKey($productId)->lockForUpdate()->first();

        if (! $sp) {
            throw new InventoryException(sprintf('Sản phẩm "%s" không còn tồn tại.', $ten));
        }

        if ($sp->variants()->where('is_active', true)->exists()) {
            throw new InventoryException(sprintf(
                'Sản phẩm "%s" có quy cách — phải chọn quy cách cụ thể.',
                $sp->name,
            ));
        }

        if (! $sp->track_inventory) {
            throw new InventoryException(sprintf(
                'Sản phẩm "%s" không bật theo dõi tồn kho nên không điều chỉnh kho được.',
                $sp->name,
            ));
        }

        return $sp;
    }
}
