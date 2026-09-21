<?php

namespace App\Services\AI;

use App\Enums\TraitType;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Coupon\CouponWallet;
use App\Services\Gift\GiftResolver;
use App\Services\Promotion\ActivePromotionProvider;
use App\Services\Recommendation\PlantAdvisor;
use App\Services\Search\ProductSearch;
use App\Services\Shop\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Dữ liệu cửa hàng đưa cho AI để trả lời MỘT câu hỏi. */
class AdvisorContext
{
    public function __construct(
        private readonly ProductSearch $search,
        private readonly GiftResolver $qua,
    ) {
    }

    public function xayDung(string $cauHoi, ?User $user, ?string $cauTruoc = null): string
    {
        $sanPham = $this->timSanPham($cauHoi);

        if ($sanPham->isEmpty() && $cauTruoc !== null) {
            $sanPham = $this->timSanPham($cauTruoc);
        }

        $phan = [
            "SẢN PHẨM LIÊN QUAN ĐẾN CÂU HỎI:\n" . ($sanPham->isEmpty()
                ? '(không tìm thấy sản phẩm nào khớp câu hỏi)'
                : $sanPham->map(fn (Product $p) => $this->moTa($p))->implode("\n")),
        ];

        $deCham = app(PlantAdvisor::class)->forBeginners(5);
        if ($deCham->isNotEmpty()) {
            $phan[] = "CÂY DỄ CHĂM ĐANG BÁN:\n" . $deCham
                ->map(fn (Product $p) => '- ' . $p->name . ' — ' . $this->gia($p))
                ->implode("\n");
        }

        $khuyenMai = app(ActivePromotionProvider::class)->dangChay(3);
        $phan[] = "KHUYẾN MẠI ĐANG CHẠY:\n" . ($khuyenMai->isEmpty()
            ? '(không có)'
            : $khuyenMai->map(fn ($km) => '- ' . $km->name . ' — ' . $km->headlineDiscount()
                . ($km->endsInText() ? ' (' . $km->endsInText() . ')' : ''))->implode("\n"));

        $ma = app(CouponWallet::class)->claimableFor($user)->take(5);
        $phan[] = "MÃ GIẢM GIÁ CÔNG KHAI:\n" . ($ma->isEmpty()
            ? '(không có)'
            : $ma->map(fn ($c) => '- ' . $c->code . ': ' . $c->name . ' (' . $c->conditionText() . ')')->implode("\n"));

        $phan[] = $this->khach($user);

        return implode("\n\n", $phan);
    }

    private function timSanPham(string $cauHoi): Collection
    {
        $terms = $this->search->terms($cauHoi);

        if ($terms->isEmpty()) {
            return collect();
        }

        $toiDa = (int) config('ai.max_products', 8);

        $truyVan = fn () => Product::query()
            ->with(['category', 'promotions', 'traits'])
            ->whereIn('status', ['active', 'out_of_stock']);

        $q = $truyVan();
        $this->search->filter($q, $terms);
        $this->search->orderByRelevance($q, $terms);
        $ket = $q->limit($toiDa)->get();

        if ($ket->isEmpty() && $terms->hasMultipleTokens()) {
            $q = $truyVan();
            $this->search->filter($q, $terms, matchAll: false);
            $this->search->orderByRelevance($q, $terms);
            $ket = $q->limit($toiDa)->get();
        }

        return $ket;
    }

    private function moTa(Product $p): string
    {
        $dong = ['- ' . $p->name
            . ($p->category ? ' (danh mục: ' . $p->category->name . ')' : '')
            . ' — ' . $this->gia($p)
            . ' — ' . $this->tonKho($p)
            . ' — trang sản phẩm: ' . route('shop.products.show', $p)];

        $quyCach = $p->variants()->where('is_active', true)->orderBy('sort_order')->get(['name', 'price', 'track_inventory', 'stock_quantity']);
        if ($quyCach->isNotEmpty()) {
            $dong[] = '  Quy cách: ' . $quyCach->map(fn ($v) => $v->name
                . ($v->price !== null ? ' ' . Money::format((string) $v->price) : '')
                . ($v->track_inventory ? ((int) $v->stock_quantity > 0 ? ' (còn ' . $v->stock_quantity . ')' : ' (hết hàng)') : ''))
                ->implode('; ');
        }

        foreach ([TraitType::Occasion, TraitType::Season] as $loai) {
            $giaTri = $p->traitValues($loai);

            if ($giaTri !== []) {
                $dong[] = '  ' . $loai->label() . ': ' . implode(', ', array_map(fn ($v) => $loai->labelFor($v), $giaTri));
            }
        }

        $nhan = $p->careProfile()->fields();
        $cham = collect($p->careEntries())
            ->map(fn ($giaTri, $khoa) => ($nhan[$khoa]['label'] ?? $khoa) . ': ' . (is_scalar($giaTri) ? $giaTri : json_encode($giaTri, JSON_UNESCAPED_UNICODE)))
            ->implode('; ');
        if ($cham !== '') {
            $dong[] = '  Chăm sóc: ' . $cham;
        }

        $quaKem = $this->qua->choSanPham($p);
        if ($quaKem->isNotEmpty()) {
            $dong[] = '  Quà tặng kèm: ' . $quaKem->map(fn ($pg) => $pg->giftItem->name . ' (' . $pg->moTaLuat() . ')')->implode('; ');
        }

        if ($p->short_description) {
            $dong[] = '  Mô tả: ' . Str::limit(strip_tags((string) $p->short_description), 200);
        }

        return implode("\n", $dong);
    }

    private function gia(Product $p): string
    {
        $gia = $p->price();

        if ($gia->finalPrice === null) {
            return 'liên hệ báo giá';
        }

        return 'giá ' . Money::format($gia->finalPrice)
            . ($gia->isDiscounted() ? ' (giá gốc ' . Money::format($gia->basePrice) . ($gia->promotion ? ', ' . $gia->promotion->name : '') . ')' : '');
    }

    private function tonKho(Product $p): string
    {
        if ($p->status === 'out_of_stock') {
            return 'tạm hết hàng';
        }

        if (! $p->track_inventory) {
            return 'còn hàng (không giới hạn số lượng)';
        }

        return (int) $p->stock_quantity > 0 ? 'còn ' . $p->stock_quantity . ' sản phẩm' : 'tạm hết hàng';
    }

    private function khach(?User $user): string
    {
        if ($user === null) {
            return 'KHÁCH: chưa đăng nhập (không có dữ liệu cá nhân).';
        }

        $yeuThich = Product::query()
            ->whereIn('id', $user->wishlists()->select('product_id'))
            ->limit(10)
            ->pluck('name');

        $don = Order::query()
            ->where('user_id', $user->id)
            ->with('items:id,order_id,product_name,quantity,is_gift')
            ->latest('id')
            ->limit(3)
            ->get(['id', 'order_number', 'status', 'created_at']);

        $dong = ['KHÁCH ĐANG HỎI (chỉ dữ liệu của chính khách này):'];
        $dong[] = '- Yêu thích: ' . ($yeuThich->isEmpty() ? '(chưa có)' : $yeuThich->implode(', '));
        $dong[] = '- Đơn gần đây: ' . ($don->isEmpty() ? '(chưa có)' : $don->map(fn (Order $o) => $o->order_number
            . ' [' . $o->status->label() . '] '
            . $o->items->where('is_gift', false)->map(fn ($i) => $i->product_name . ' ×' . $i->quantity)->implode(', '))
            ->implode(' | '));

        return implode("\n", $dong);
    }
}
