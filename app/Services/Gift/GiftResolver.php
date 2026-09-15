<?php

namespace App\Services\Gift;

use App\Enums\GiftCampaignKind;
use App\Enums\OrderStatus;
use App\Enums\PromotionStatus;
use App\Models\GiftCampaign;
use App\Models\MemberTier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Checkout\CheckoutBasket;
use App\Services\Loyalty\MemberTierResolver;
use App\Services\Shop\Money;
use Illuminate\Support\Collection;

/**
 * Đơn này được tặng gì — NƠI DUY NHẤT trả lời, cho cả màn hình và lúc ghi đơn.
 * ============================================================
 * Trang thanh toán hỏi để HIỆN quà; OrderService hỏi lại lúc ghi đơn (rồi
 * mới khoá kho và suất). Hai nơi hai bộ luật thì khách thấy quà trên màn
 * hình mà đơn không có — hoặc ngược lại.
 *
 * MỖI CHƯƠNG TRÌNH TẶNG MỘT BỘ MỖI ĐƠN. Mua 10 cây không thành 10 phần quà:
 * quà kéo người mua, không phải hàng bán kèm miễn phí theo số lượng.
 *
 * KHÁCH VÃNG LAI: nhận được quà không giới hạn theo người. Quà "đơn đầu
 * tiên" hay "mỗi tài khoản N lần" thì cần tài khoản — không có tài khoản
 * thì không kiểm được, và quà giới hạn không kiểm được là quà vô hạn.
 */
class GiftResolver
{
    public function __construct(
        private readonly MemberTierResolver $hang,
    ) {
    }

    /**
     * @return Collection<int, array{campaign: GiftCampaign, quantity: int}>
     */
    public function choGio(CheckoutBasket $basket, ?User $user): Collection
    {
        if ($basket->isEmpty()) {
            return collect();
        }

        $cacChuongTrinh = $this->dangChay();

        if ($cacChuongTrinh->isEmpty()) {
            return collect();
        }

        $hangKhach = $user ? $this->hang->cua($user)['hang'] : null;

        return $cacChuongTrinh
            ->filter(fn (GiftCampaign $ct) => $this->lyDoKhong($ct, $basket, $user, $hangKhach) === null)
            ->map(fn (GiftCampaign $ct) => ['campaign' => $ct, 'quantity' => (int) $ct->gift_quantity])
            ->values();
    }

    /**
     * Vì sao đơn này không nhận được quà của chương trình; null = nhận được.
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

        if ($ct->kind === GiftCampaignKind::KemSanPham) {
            $soLuong = (int) $basket->lines
                ->filter(fn ($l) => (int) $l->product->id === (int) $ct->trigger_product_id)
                ->sum(fn ($l) => $l->quantity);

            if ($soLuong < max(1, (int) $ct->trigger_min_quantity)) {
                return 'Cần mua từ ' . max(1, (int) $ct->trigger_min_quantity) . ' sản phẩm kèm quà.';
            }
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

    /**
     * Quà kèm đang chạy của một sản phẩm — để trang sản phẩm nói trước.
     *
     * @return Collection<int, GiftCampaign>
     */
    public function choSanPham(Product $product): Collection
    {
        return $this->dangChay()
            ->filter(fn (GiftCampaign $ct) => $ct->kind === GiftCampaignKind::KemSanPham
                && (int) $ct->trigger_product_id === (int) $product->id
                && $ct->giftItem?->is_active
                && ($ct->giftItem->tonKhoCon() === null || $ct->giftItem->tonKhoCon() >= (int) $ct->gift_quantity))
            ->values();
    }

    /** @return Collection<int, GiftCampaign> */
    private function dangChay(): Collection
    {
        return GiftCampaign::query()
            ->where('status', PromotionStatus::Active->value)
            ->with(['giftItem.product', 'giftItem.variant', 'minMemberTier'])
            ->orderBy('id')
            ->get()
            ->filter(fn (GiftCampaign $ct) => $ct->isRunning())
            ->values();
    }
}
