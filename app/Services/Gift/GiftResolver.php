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

    private array $vatPhamRieng = [];

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
        if (! $km->isRunning()) {
            return 'Chương trình quà không còn chạy.';
        }

        if ($km->conSuat() === 0) {
            return 'Chương trình đã hết suất quà.';
        }

        if ($this->quaCua($km, $basket)->isEmpty()) {
            return $km->products->isNotEmpty()
                ? 'Quà dành cho đơn có sản phẩm được tặng quà của chương trình.'
                : 'Quà đã được tặng hết hoặc đang tạm ngưng.';
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
                ->flatMap(fn (Promotion $km) => $km->products
                    ->filter(fn (Product $sp) => $this->quaCuaDong($km, $sp) !== null)
                    ->pluck('id'))
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

    /** Quà giỏ này CHỈ còn thiếu tiền mới nhận được: [khuyến mại, quà, số tiền còn thiếu]. */
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
                'qua' => $this->quaCua($km, $basket),
                'con_thieu' => bcsub((string) $km->min_order_amount, $basket->itemsTotal(), 2),
            ])
            ->sortBy(fn ($d) => (float) $d['con_thieu'])
            ->values();
    }

    /** Quà theo chương trình khuyến mại mà sản phẩm này mang lại: [khuyến mại, vật phẩm, số lượng]. */
    public function khuyenMaiQuaCho(Product $product): Collection
    {
        return $this->khuyenMaiQua()
            ->filter(fn (Promotion $km) => $km->conSuat() !== 0)
            ->map(function (Promotion $km) use ($product) {
                $dong = $km->products->firstWhere('id', $product->id);

                $qua = $dong !== null
                    ? $this->quaCuaDong($km, $dong)
                    : ($km->products->isEmpty() && $km->laTangQua() ? $this->quaMacDinh($km) : null);

                return $qua === null ? null : ['khuyen_mai' => $km] + $qua;
            })
            ->filter()
            ->values();
    }

    /** Quà một chương trình mang lại cho giỏ này (chưa xét điều kiện đơn / khách). */
    private function quaCua(Promotion $km, CheckoutBasket $basket): Collection
    {
        $dongs = $basket->lines;
        $ket = collect();
        $canMacDinh = $km->products->isEmpty() && $km->laTangQua();

        foreach ($km->products as $sp) {
            if ($km->kieuCho($sp->pivot) !== PromotionType::TangQua) {
                continue;
            }

            $khop = $dongs->filter(fn ($l) => (int) $l->product->id === (int) $sp->id);

            if ($khop->isEmpty()) {
                continue;
            }

            if ($sp->pivot->gift_item_id === null) {
                $canMacDinh = true;

                continue;
            }

            $qua = $this->quaCuaDong($km, $sp);

            if ($qua !== null) {
                $dau = $khop->first();
                $ket->push($this->dongQua($km, $qua, (int) $sp->id, $dau->variant?->id, self::khoaDong((int) $sp->id, $dau->variant?->id)));
            }
        }

        if ($canMacDinh && ($qua = $this->quaMacDinh($km)) !== null) {
            $ket->push($this->dongQua($km, $qua, null, null, null));
        }

        return $ket;
    }

    /** Quà của một dòng sản phẩm: quà riêng của dòng, không có thì quà chung của chương trình. */
    private function quaCuaDong(Promotion $km, Product $sp): ?array
    {
        if ($km->kieuCho($sp->pivot) !== PromotionType::TangQua) {
            return null;
        }

        if ($sp->pivot->gift_item_id === null) {
            return $this->quaMacDinh($km);
        }

        return $this->conTang(
            $this->vatPhamRieng[$sp->pivot->gift_item_id] ?? null,
            (int) ($sp->pivot->gift_quantity ?? $km->gift_quantity ?? 1),
        );
    }

    private function quaMacDinh(Promotion $km): ?array
    {
        return $km->laTangQua() ? $this->conTang($km->giftItem, (int) $km->gift_quantity) : null;
    }

    private function conTang(?GiftItem $vat, int $soLuong): ?array
    {
        if ($vat === null || ! $vat->is_active || $soLuong < 1) {
            return null;
        }

        $con = $vat->tonKhoCon();

        return $con !== null && $con < $soLuong ? null : ['item' => $vat, 'quantity' => $soLuong];
    }

    private function dongQua(Promotion $km, array $qua, ?int $choSanPham, ?int $choQuyCach, ?string $dongCha): array
    {
        return [
            'nguon' => 'chuong_trinh',
            'campaign' => $km,
            'product_gift' => null,
            'item' => $qua['item'],
            'quantity' => $qua['quantity'],
            'for_product_id' => $choSanPham,
            'for_variant_id' => $choQuyCach,
            'dong_cha' => $dongCha,
        ];
    }

    private function khuyenMaiQua(): Collection
    {
        if ($this->khuyenMaiQua !== null) {
            return $this->khuyenMaiQua;
        }

        $cacKm = Promotion::query()
            ->activeNow()
            ->where(fn ($q) => $q
                ->where('type', PromotionType::TangQua->value)
                ->orWhereHas('products', fn ($p) => $p->where('promotion_product.discount_type', PromotionType::TangQua->value)))
            ->with(['giftItem.product', 'giftItem.variant', 'minMemberTier', 'products:id'])
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->filter(fn (Promotion $km) => $km->isRunning())
            ->values();

        $this->vatPhamRieng = GiftItem::query()
            ->with(['product', 'variant'])
            ->whereIn('id', $cacKm->flatMap(fn (Promotion $km) => $km->products->pluck('pivot.gift_item_id'))->filter()->unique()->all())
            ->get()
            ->keyBy('id')
            ->all();

        return $this->khuyenMaiQua = $cacKm;
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
            ->flatMap(fn (Promotion $km) => $this->quaCua($km, $basket))
            ->values();
    }
}
