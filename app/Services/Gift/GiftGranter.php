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
 *     (used_count < total_limit), cùng cách với lượt mã giảm giá;
 *   - tồn kho của quà: khoá dòng rồi mới trừ, cùng cách với hàng bán —
 *     không có cơ chế tồn kho thứ hai.
 *
 * HẾT GIỮA CHỪNG THÌ BỎ QUÀ, KHÔNG HỎNG ĐƠN: quà là thứ cho thêm, cuộn một đơn
 * mua cây vì túi vải tặng kèm vừa hết là phạt khách vì món họ không trả tiền.
 *
 * CHỤP vào dòng quà: tên, quy cách, mã SKU, trị giá — lịch sử đơn không phụ
 * thuộc dữ liệu sản phẩm / vật phẩm hiện tại. Quà kèm sản phẩm nằm dưới
 * đúng dòng hàng (đúng quy cách) đã sinh ra nó.
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

            $order->items()->create([
                'product_id' => $vat->product_id,
                'product_variant_id' => $vat->product_variant_id,
                'product_name' => $vat->name,
                'product_sku' => $vat->variant?->code ?? $vat->product?->product_code,
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
                'parent_item_id' => $this->dongCha($order, $dong),
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

    /** Dòng hàng đã sinh ra quà — đúng sản phẩm, và đúng quy cách của dòng đầu tiên khớp. */
    private function dongCha(Order $order, array $dong): ?int
    {
        if ($dong['for_product_id'] === null) {
            return null;
        }

        return $order->items()
            ->where('is_gift', false)
            ->where('product_id', $dong['for_product_id'])
            ->when(
                $dong['for_variant_id'] !== null,
                fn ($q) => $q->where('product_variant_id', $dong['for_variant_id']),
                fn ($q) => $q->whereNull('product_variant_id'),
            )
            ->orderBy('id')
            ->value('id');
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

        // Không bao giờ để quà miễn phí làm âm kho.
        if ((int) $dong->stock_quantity < $soLuong) {
            return false;
        }

        $dong->decrement('stock_quantity', $soLuong);

        return true;
    }
}
