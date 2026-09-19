<?php

namespace App\Services\Gift;

use App\Enums\GiftStockRule;
use App\Enums\OrderStatus;
use App\Enums\PromotionType;
use App\Models\GiftItem;
use App\Models\MemberTier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductGift;
use App\Models\Promotion;
use App\Models\User;
use App\Services\Checkout\CheckoutBasket;
use App\Services\Loyalty\MemberTierResolver;
use App\Services\Shop\Money;
use Illuminate\Support\Collection;

/** Đơn / giỏ này được tặng gì — NƠI DUY NHẤT tính quà. */
class GiftResolver
{
    private ?Collection $khuyenMaiQua = null;

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

    public function lyDoKhong(Promotion $km, CheckoutBasket $basket, ?User $user, ?MemberTier $hangKhach = null, bool $boQuaDonToiThieu = false): ?string
    {
        if (! $km->laTangQua() || ! $km->isRunning()) {
            return 'Chương trình quà không còn chạy.';
        }

        if ($km->conSuat() === 0) {
            return 'Chương trình đã hết suất quà.';
        }

        $vatPham = $km->giftItem;

        if ($vatPham === null || ! $vatPham->is_active) {
            return 'Quà đang tạm ngưng.';
        }

        $con = $vatPham->tonKhoCon();

        if ($con !== null && $con < (int) $km->gift_quantity) {
            return 'Quà đã được tặng hết.';
        }

        $canMua = $km->products->pluck('id');

        if ($canMua->isNotEmpty() && $basket->lines->doesntContain(fn ($l) => $canMua->contains($l->product->id))) {
            return 'Quà dành cho đơn có sản phẩm của chương trình.';
        }

        if (! $boQuaDonToiThieu && $km->min_order_amount !== null && bccomp($basket->itemsTotal(), (string) $km->min_order_amount, 2) < 0) {
            return 'Quà dành cho đơn từ ' . Money::format((string) $km->min_order_amount) . '.';
        }

        if ($km->min_member_tier_id !== null && ($can = $km->minMemberTier) !== null) {
            $hangKhach ??= $user ? $this->hang->cua($user)['hang'] : null;

            if ($hangKhach === null || bccomp((string) $hangKhach->min_spend, (string) $can->min_spend, 2) < 0) {
                return 'Quà dành cho thành viên hạng ' . $can->name . ' trở lên.';
            }
        }

        if ($km->first_order_only || $km->per_user_limit !== null) {
            if ($user === null) {
                return 'Đăng nhập để nhận quà này.';
            }

            if ($km->first_order_only && Order::query()
                ->where('user_id', $user->id)
                ->where('status', '!=', OrderStatus::Cancelled->value)
                ->exists()) {
                return 'Quà chỉ dành cho đơn đầu tiên.';
            }

            if ($km->per_user_limit !== null && $this->daNhan($km, $user) >= $km->per_user_limit) {
                return 'Bạn đã nhận đủ quà của chương trình này.';
            }
        }

        return null;
    }

    public function daNhan(Promotion $km, User $user): int
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.is_gift', true)
            ->where('order_items.gift_promotion_id', $km->id)
            ->where('orders.user_id', $user->id)
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->distinct()
            ->count('order_items.order_id');
    }

    /** Id các sản phẩm đang có quà (quà kèm hoặc chương trình tặng quà), tính một lần mỗi request — cho nhãn trên thẻ sản phẩm. */
    public static function sanPhamCoQua(): array
    {
        $req = request();

        if (! $req->attributes->has('san_pham_co_qua')) {
            $req->attributes->set('san_pham_co_qua', app(self::class)->tinhSanPhamCoQua());
        }

        return $req->attributes->get('san_pham_co_qua');
    }

    private function tinhSanPhamCoQua(): array
    {
        return array_flip(array_merge(
            ProductGift::query()
                ->where('is_active', true)
                ->whereHas('giftItem', fn ($q) => $q->where('is_active', true))
                ->pluck('product_id')
                ->all(),
            $this->khuyenMaiQua()
                ->flatMap(fn (Promotion $km) => $km->products->pluck('id'))
                ->all(),
        ));
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

    /** Quà giỏ này CHỈ còn thiếu tiền mới nhận được: [khuyến mại, số tiền còn thiếu]. */
    public function goiYMuaThem(CheckoutBasket $basket, ?User $user): Collection
    {
        if ($basket->isEmpty()) {
            return collect();
        }

        $hangKhach = $user ? $this->hang->cua($user)['hang'] : null;

        return $this->khuyenMaiQua()
            ->filter(fn (Promotion $km) => $km->min_order_amount !== null
                && bccomp($basket->itemsTotal(), (string) $km->min_order_amount, 2) < 0
                && $this->lyDoKhong($km, $basket, $user, $hangKhach, true) === null)
            ->map(fn (Promotion $km) => [
                'khuyen_mai' => $km,
                'con_thieu' => bcsub((string) $km->min_order_amount, $basket->itemsTotal(), 2),
            ])
            ->sortBy(fn ($d) => (float) $d['con_thieu'])
            ->values();
    }

    /** Chương trình tặng quà đang chạy mà sản phẩm này góp phần được quà (có trong danh sách, hoặc chương trình áp mọi đơn). */
    public function khuyenMaiQuaCho(Product $product): Collection
    {
        return $this->khuyenMaiQua()
            ->filter(fn (Promotion $km) => $km->giftItem?->is_active && $km->conSuat() !== 0
                && ($km->products->isEmpty() || $km->products->contains('id', $product->id)))
            ->values();
    }

    private function khuyenMaiQua(): Collection
    {
        return $this->khuyenMaiQua ??= Promotion::query()
            ->activeNow()
            ->where('type', PromotionType::TangQua->value)
            ->with(['giftItem.product', 'giftItem.variant', 'minMemberTier', 'products:id'])
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->filter(fn (Promotion $km) => $km->isRunning())
            ->values();
    }

    private function quaChuongTrinh(CheckoutBasket $basket, ?User $user): Collection
    {
        $cacChuongTrinh = $this->khuyenMaiQua();

        if ($cacChuongTrinh->isEmpty()) {
            return collect();
        }

        $hangKhach = $user ? $this->hang->cua($user)['hang'] : null;

        return $cacChuongTrinh
            ->filter(fn (Promotion $km) => $this->lyDoKhong($km, $basket, $user, $hangKhach) === null)
            ->map(fn (Promotion $km) => [
                'nguon' => 'chuong_trinh',
                'campaign' => $km,
                'product_gift' => null,
                'item' => $km->giftItem,
                'quantity' => (int) $km->gift_quantity,
                'for_product_id' => null,
                'for_variant_id' => null,
                'dong_cha' => null,
            ])
            ->values();
    }
}
