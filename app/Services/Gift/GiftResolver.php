<?php

namespace App\Services\Gift;

use App\Enums\GiftCampaignKind;
use App\Enums\OrderStatus;
use App\Enums\PromotionStatus;
use App\Models\GiftCampaign;
use App\Models\GiftItem;
use App\Models\MemberTier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductGift;
use App\Models\User;
use App\Services\Checkout\CheckoutBasket;
use App\Services\Loyalty\MemberTierResolver;
use App\Services\Shop\Money;
use Illuminate\Support\Collection;

/**
 * Đơn này được tặng gì — NƠI DUY NHẤT trả lời, cho cả màn hình và lúc ghi đơn.
 * ============================================================
 * HAI NGUỒN QUÀ:
 *
 *   - QUÀ KÈM SẢN PHẨM (product_gifts): quà mặc định của chính món hàng.
 *     Mua mỗi N món thì tặng M quà, không điều kiện gì khác. Quà còn ít hơn
 *     số được tặng thì tặng phần còn lại — khách vẫn nhận được gì đó thay
 *     vì không có gì.
 *
 *   - QUÀ THEO CHƯƠNG TRÌNH (gift_campaigns, thuộc Khuyến mại): giới hạn
 *     suất, thời gian, hạng, đơn đầu tiên, đơn từ X đồng. Mỗi chương trình
 *     tặng một bộ mỗi đơn. Quà "đơn đầu tiên" / "mỗi tài khoản N lần" cần
 *     tài khoản — không kiểm được thì là quà vô hạn.
 *
 * Trang thanh toán hỏi để HIỆN quà; OrderService hỏi lại lúc ghi đơn (rồi
 * mới khoá kho và suất). Hai nơi hai bộ luật thì khách thấy quà trên màn
 * hình mà đơn không có — hoặc ngược lại.
 */
class GiftResolver
{
    public function __construct(
        private readonly MemberTierResolver $hang,
    ) {
    }

    /**
     * @return Collection<int, array{nguon: string, campaign: ?GiftCampaign, product_gift: ?ProductGift, item: GiftItem, quantity: int, for_product_id: ?int}>
     */
    public function choGio(CheckoutBasket $basket, ?User $user): Collection
    {
        if ($basket->isEmpty()) {
            return collect();
        }

        return $this->quaKemSanPham($basket)
            ->concat($this->quaChuongTrinh($basket, $user))
            ->values();
    }

    /**
     * Quà mặc định đang tặng của một sản phẩm — để trang sản phẩm nói trước.
     *
     * @return Collection<int, ProductGift>
     */
    public function choSanPham(Product $product): Collection
    {
        return ProductGift::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->with(['giftItem.product', 'giftItem.variant'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (ProductGift $pg) => $pg->giftItem?->is_active
                && ($pg->giftItem->tonKhoCon() === null || $pg->giftItem->tonKhoCon() > 0))
            ->values();
    }

    /**
     * Vì sao đơn này không nhận được quà của CHƯƠNG TRÌNH; null = nhận được.
     */
    public function lyDoKhong(GiftCampaign $ct, CheckoutBasket $basket, ?User $user, ?MemberTier $hangKhach = null): ?string
    {
        if (! $ct->isRunning()) {
            return 'Chương trình quà không còn chạy.';
        }

        $vatPham = $ct->giftItem;

        if ($vatPham === null || ! $vatPham->is_active) {
            return 'Quà đang tạm ngưng.';
        }

        $con = $vatPham->tonKhoCon();

        if ($con !== null && $con < (int) $ct->gift_quantity) {
            return 'Quà đã được tặng hết.';
        }

        if ($ct->min_order_amount !== null && bccomp($basket->itemsTotal(), (string) $ct->min_order_amount, 2) < 0) {
            return 'Quà dành cho đơn từ ' . Money::format((string) $ct->min_order_amount) . '.';
        }

        if ($ct->min_member_tier_id !== null && ($can = $ct->minMemberTier) !== null) {
            $hangKhach ??= $user ? $this->hang->cua($user)['hang'] : null;

            if ($hangKhach === null || bccomp((string) $hangKhach->min_spend, (string) $can->min_spend, 2) < 0) {
                return 'Quà dành cho thành viên hạng ' . $can->name . ' trở lên.';
            }
        }

        if ($ct->first_order_only || $ct->per_user_limit !== null) {
            if ($user === null) {
                return 'Đăng nhập để nhận quà này.';
            }

            // "Đơn đầu tiên" = chưa có đơn nào không bị huỷ. Đơn huỷ không tính là đã mua.
            if ($ct->first_order_only && Order::query()
                ->where('user_id', $user->id)
                ->where('status', '!=', OrderStatus::Cancelled->value)
                ->exists()) {
                return 'Quà chỉ dành cho đơn đầu tiên.';
            }

            if ($ct->per_user_limit !== null && $this->daNhan($ct, $user) >= $ct->per_user_limit) {
                return 'Bạn đã nhận đủ quà của chương trình này.';
            }
        }

        return null;
    }

    /** Số lần khách đã nhận quà của chương trình — đếm từ đơn không bị huỷ. */
    public function daNhan(GiftCampaign $ct, User $user): int
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.is_gift', true)
            ->where('order_items.gift_campaign_id', $ct->id)
            ->where('orders.user_id', $user->id)
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->distinct()
            ->count('order_items.order_id');
    }

    private function quaKemSanPham(CheckoutBasket $basket): Collection
    {
        $soLuong = [];

        foreach ($basket->lines as $l) {
            $soLuong[$l->product->id] = ($soLuong[$l->product->id] ?? 0) + $l->quantity;
        }

        return ProductGift::query()
            ->whereIn('product_id', array_keys($soLuong))
            ->where('is_active', true)
            ->with(['giftItem.product', 'giftItem.variant'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (ProductGift $pg) use ($soLuong) {
                $vat = $pg->giftItem;

                if ($vat === null || ! $vat->is_active) {
                    return null;
                }

                $n = $pg->soQuaCho($soLuong[$pg->product_id] ?? 0);
                $con = $vat->tonKhoCon();

                if ($con !== null) {
                    $n = min($n, $con);
                }

                return $n > 0 ? [
                    'nguon' => 'san_pham',
                    'campaign' => null,
                    'product_gift' => $pg,
                    'item' => $vat,
                    'quantity' => $n,
                    'for_product_id' => (int) $pg->product_id,
                ] : null;
            })
            ->filter()
            ->values();
    }

    private function quaChuongTrinh(CheckoutBasket $basket, ?User $user): Collection
    {
        $cacChuongTrinh = GiftCampaign::query()
            ->where('status', PromotionStatus::Active->value)
            ->where('kind', GiftCampaignKind::ChuongTrinh->value)
            ->with(['giftItem.product', 'giftItem.variant', 'minMemberTier'])
            ->orderBy('id')
            ->get()
            ->filter(fn (GiftCampaign $ct) => $ct->isRunning());

        if ($cacChuongTrinh->isEmpty()) {
            return collect();
        }

        $hangKhach = $user ? $this->hang->cua($user)['hang'] : null;

        return $cacChuongTrinh
            ->filter(fn (GiftCampaign $ct) => $this->lyDoKhong($ct, $basket, $user, $hangKhach) === null)
            ->map(fn (GiftCampaign $ct) => [
                'nguon' => 'chuong_trinh',
                'campaign' => $ct,
                'product_gift' => null,
                'item' => $ct->giftItem,
                'quantity' => (int) $ct->gift_quantity,
                'for_product_id' => null,
            ])
            ->values();
    }
}
