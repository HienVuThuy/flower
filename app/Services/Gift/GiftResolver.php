<?php

namespace App\Services\Gift;

use App\Enums\GiftCampaignKind;
use App\Enums\GiftStockRule;
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

/** Đơn / giỏ này được tặng gì — NƠI DUY NHẤT tính quà. */
class GiftResolver
{
    public function __construct(
        private readonly MemberTierResolver $hang,
    ) {
    }

    public function choGio(CheckoutBasket $basket, ?User $user): Collection
    {
        if ($basket->isEmpty()) {
            return collect();
        }

        return $this->quaKemSanPham($basket)
            ->concat($this->quaChuongTrinh($basket, $user))
            ->values();
    }

    public function theoDong(CheckoutBasket $basket): array
    {
        $ket = [];

        foreach ($this->quaKemSanPham($basket) as $dong) {
            $ket[$dong['dong_cha']][] = $dong;
        }

        return $ket;
    }

    public static function khoaDong(int $productId, ?int $variantId): string
    {
        return $productId . ':' . ($variantId ?? '');
    }

    public function choSanPham(Product $product): Collection
    {
        return ProductGift::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->with(['giftItem.product', 'giftItem.variant', 'variant'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (ProductGift $pg) => $pg->giftItem?->is_active
                && ($pg->giftItem->tonKhoCon() === null || $pg->giftItem->tonKhoCon() > 0))
            ->values();
    }

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
        if ($basket->isEmpty()) {
            return collect();
        }

        $dongs = $basket->lines->values();

        return ProductGift::query()
            ->whereIn('product_id', $dongs->map(fn ($l) => $l->product->id)->unique()->all())
            ->where('is_active', true)
            ->with(['giftItem.product', 'giftItem.variant'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (ProductGift $pg) use ($dongs) {
                $vat = $pg->giftItem;

                if ($vat === null || ! $vat->is_active) {
                    return null;
                }

                $khop = $dongs->filter(fn ($l) => $pg->apDungCho((int) $l->product->id, $l->variant?->id));

                if ($khop->isEmpty()) {
                    return null;
                }

                $n = $pg->soQuaCho((int) $khop->sum(fn ($l) => $l->quantity));
                $con = $vat->tonKhoCon();

                if ($con !== null && $con < $n) {
                    $n = $pg->khi_thieu_kho === GiftStockRule::KhongTang ? 0 : $con;
                }

                if ($n <= 0) {
                    return null;
                }

                $dau = $khop->first();

                return [
                    'nguon' => 'san_pham',
                    'campaign' => null,
                    'product_gift' => $pg,
                    'item' => $vat,
                    'quantity' => $n,
                    'for_product_id' => (int) $pg->product_id,
                    'for_variant_id' => $pg->product_variant_id !== null ? (int) $pg->product_variant_id : ($dau->variant?->id),
                    'dong_cha' => self::khoaDong((int) $dau->product->id, $dau->variant?->id),
                ];
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
                'for_variant_id' => null,
                'dong_cha' => null,
            ])
            ->values();
    }
}
