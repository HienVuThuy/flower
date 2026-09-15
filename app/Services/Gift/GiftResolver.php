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

/**
 * Đơn / giỏ này được tặng gì — NƠI DUY NHẤT tính quà.
 * ============================================================
 * QUÀ LÀ QUYỀN ĐƯỢC SUY RA từ hàng trong giỏ, không phải một món hàng khách
 * tự thêm hay xoá. Không lưu vào giỏ: mỗi lần hỏi là tính lại từ số lượng
 * hiện tại — giảm Sen đá ×3 → ×1 thì quà tự về ×1, xoá Sen đá thì quà mất.
 * Không có trạng thái "sản phẩm ×1, quà ×3" nào tồn tại được.
 *
 * HAI NGUỒN QUÀ:
 *
 *   - QUÀ KÈM SẢN PHẨM (product_gifts): quà mặc định của món hàng hoặc của
 *     một quy cách. ⌊mua ÷ N⌋ × M, kẹp theo "tối đa mỗi đơn", rồi theo tồn
 *     kho quà với luật riêng của món quà (tặng phần còn lại / không tặng).
 *     KHÔNG liên quan mã giảm giá, chương trình, sale hay giảm theo hạng.
 *
 *   - QUÀ THEO CHƯƠNG TRÌNH (gift_campaigns, tab Khuyến mại): giới hạn suất,
 *     thời gian, hạng, đơn đầu tiên, đơn từ X đồng. Một bộ mỗi đơn.
 *
 * Giỏ hàng, trang thanh toán và OrderService cùng hỏi ở đây.
 */
class GiftResolver
{
    public function __construct(
        private readonly MemberTierResolver $hang,
    ) {
    }

    /**
     * @return Collection<int, array{nguon: string, campaign: ?GiftCampaign, product_gift: ?ProductGift, item: GiftItem, quantity: int, for_product_id: ?int, for_variant_id: ?int, dong_cha: ?string}>
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
     * Quà kèm sản phẩm xếp theo DÒNG HÀNG đã sinh ra nó — để giỏ hàng hiện quà
     * ngay dưới món. Khoá dòng: "product_id:variant_id" (quy cách trống = '').
     *
     * @return array<string, list<array{item: GiftItem, quantity: int, product_gift: ProductGift}>>
     */
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
            ->with(['giftItem.product', 'giftItem.variant', 'variant'])
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

                // Quà "mọi quy cách" cộng số lượng của mọi quy cách; quà theo quy cách chỉ đếm đúng quy cách đó.
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
