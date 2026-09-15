<?php

namespace App\Services\Gift;

use App\Models\GiftCampaign;
use App\Models\GiftItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Checkout\CheckoutBasket;
use Illuminate\Support\Facades\DB;

/**
 * Ghi quà vào đơn và trả lại khi đơn huỷ. GỌI TRONG TRANSACTION CỦA ĐƠN.
 * ============================================================
 * AI ĐƯỢC QUÀ do GiftResolver quyết định — ở đây chỉ GIỮ CHỖ cho đúng:
 *
 *   - suất của chương trình: một câu UPDATE có điều kiện
 *     (used_count < total_limit), cùng cách với lượt mã giảm giá — hai
 *     đơn cùng lúc tranh suất cuối thì cơ sở dữ liệu phân xử;
 *   - tồn kho của quà: khoá dòng rồi mới trừ, cùng cách với hàng bán.
 *
 * HẾT GIỮA CHỪNG THÌ BỎ QUÀ, KHÔNG HỎNG ĐƠN. Khác mã giảm giá (thiếu lượt
 * thì cuộn cả đơn): quà là thứ cho thêm; cuộn một đơn mua cây vì túi vải
 * tặng kèm vừa hết là phạt khách vì một món họ không trả tiền. Trang đơn
 * liệt kê đúng quà đã thật sự nhận.
 *
 * QUÀ KÈM SẢN PHẨM nằm dưới dòng hàng đã sinh ra nó (parent_item_id).
 */
class GiftGranter
{
    public function __construct(
        private readonly GiftResolver $resolver,
    ) {
    }

    /** @return list<string> tên các quà đã ghi vào đơn */
    public function tangChoDon(Order $order, CheckoutBasket $basket, ?User $user): array
    {
        $daTang = [];

        foreach ($this->resolver->choGio($basket, $user) as $dong) {
            /** @var GiftItem $vat */
            $vat = $dong['item'];
            $soLuong = $dong['quantity'];
            /** @var GiftCampaign|null $ct */
            $ct = $dong['campaign'];

            if ($ct !== null && ! $this->giuSuat($ct)) {
                continue;
            }

            if (! $this->truKho($vat, $soLuong)) {
                // Trả lại suất vừa giữ — quà không đi thì suất không mất.
                if ($ct !== null) {
                    $this->traSuatMot($ct->id);
                }

                continue;
            }

            $cha = $dong['for_product_id'] === null ? null : $order->items()
                ->where('product_id', $dong['for_product_id'])
                ->where('is_gift', false)
                ->orderBy('id')
                ->value('id');

            $order->items()->create([
                'product_id' => $vat->product_id,
                'product_variant_id' => $vat->product_variant_id,
                'product_name' => $vat->name,
                'variant_name' => $vat->variant?->name,
                'promotion_name' => $ct?->name ?? 'Quà miễn phí',
                // Trị giá tham khảo để khách biết quà đáng bao nhiêu; tiền thật của dòng là 0.
                'unit_base_price' => $vat->value ?? '0.00',
                'unit_price' => '0.00',
                'quantity' => $soLuong,
                'line_total' => '0.00',
                'discount_amount' => '0.00',
                'is_gift' => true,
                'gift_campaign_id' => $ct?->id,
                'product_gift_id' => $dong['product_gift']?->id,
                'gift_item_id' => $vat->id,
                'parent_item_id' => $cha,
            ]);

            $daTang[] = $vat->name;
        }

        return $daTang;
    }

    /**
     * Đơn huỷ: trả suất chương trình. Kho của dòng quà đi chung đường hoàn
     * kho với hàng bán (StockReturn), không trả ở đây.
     */
    public function traSuatCuaDon(Order $order): void
    {
        $order->items()
            ->where('is_gift', true)
            ->whereNotNull('gift_campaign_id')
            ->pluck('gift_campaign_id')
            ->each(fn ($id) => $this->traSuatMot((int) $id));
    }

    private function giuSuat(GiftCampaign $ct): bool
    {
        return GiftCampaign::whereKey($ct->id)
            ->where(fn ($q) => $q->whereNull('total_limit')->orWhereColumn('used_count', '<', 'total_limit'))
            ->update(['used_count' => DB::raw('used_count + 1')]) === 1;
    }

    private function traSuatMot(int $campaignId): void
    {
        GiftCampaign::whereKey($campaignId)->where('used_count', '>', 0)->decrement('used_count');
    }

    private function truKho(GiftItem $vat, int $soLuong): bool
    {
        if (! $vat->laSanPham()) {
            return GiftItem::whereKey($vat->id)
                ->where('stock_quantity', '>=', $soLuong)
                ->decrement('stock_quantity', $soLuong) === 1;
        }

        $dong = $vat->product_variant_id !== null
            ? ProductVariant::whereKey($vat->product_variant_id)->lockForUpdate()->first()
            : Product::whereKey($vat->product_id)->lockForUpdate()->first();

        if ($dong === null) {
            return false;
        }

        if (! $dong->track_inventory) {
            return true;
        }

        if ((int) $dong->stock_quantity < $soLuong) {
            return false;
        }

        $dong->decrement('stock_quantity', $soLuong);

        return true;
    }
}
