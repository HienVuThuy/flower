<?php

namespace App\Services\Analytics;

use App\Models\Cart;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Giỏ hàng bỏ dở: khách đã chọn hàng mà không đặt. */
class AbandonedCarts
{
    public const BO_SAU_GIO = 24;

    public function baoCao(): array
    {
        $moc = now()->subHours(self::BO_SAU_GIO);

        $gio = Cart::query()
            ->whereHas('items')
            ->with(['user:id,name,email', 'items.product.promotions', 'items.variant'])
            ->get()
            ->map(function (Cart $cart) {
                $lanCuoi = collect([$cart->updated_at])
                    ->merge($cart->items->pluck('updated_at'))
                    ->filter()
                    ->max();

                return [$cart, $lanCuoi];
            })
            ->filter(fn ($p) => $p[1] !== null && $p[1]->lt($moc))
            ->reject(function ($p) {
                [$cart, $lanCuoi] = $p;

                return $cart->user_id !== null
                    && Order::where('user_id', $cart->user_id)->where('created_at', '>', $lanCuoi)->exists();
            })
            ->map(fn ($p) => $this->dongGio($p[0], $p[1]))
            ->sortByDesc(fn ($g) => (float) $g['gia_tri'])
            ->values();

        $tong = '0.00';
        foreach ($gio as $g) {
            $tong = bcadd($tong, $g['gia_tri'], 2);
        }

        $theoDoLau = ['1–3 ngày' => 0, '3–7 ngày' => 0, 'Trên 7 ngày' => 0];
        foreach ($gio as $g) {
            $theoDoLau[$g['so_ngay'] < 3 ? '1–3 ngày' : ($g['so_ngay'] < 7 ? '3–7 ngày' : 'Trên 7 ngày')]++;
        }

        return [
            'gio' => $gio,
            'tong_gia_tri' => $tong,
            'so_gio' => $gio->count(),
            'so_mon' => (int) $gio->sum('so_mon'),
            'vang_lai' => $gio->where('vang_lai', true)->count(),
            'theo_do_lau' => $theoDoLau,
            'theo_san_pham' => $this->theoSanPham($gio),
        ];
    }

    private function dongGio(Cart $cart, Carbon $lanCuoi): array
    {
        $giaTri = '0.00';
        $khongDinhGia = 0;
        $matHang = [];

        foreach ($cart->items as $item) {
            if (! $item->product) {
                continue;
            }

            $matHang[] = $item->product->name . ($item->variant ? ' — ' . $item->variant->name : '');

            $coGiaRieng = $item->variant && $item->variant->price !== null;

            if (! $coGiaRieng && $item->product->price()->finalPrice === null) {
                $khongDinhGia++;

                continue;
            }

            $giaTri = bcadd($giaTri, $item->lineTotal(), 2);
        }

        return [
            'khach' => $cart->user?->name ?? 'Khách vãng lai',
            'email' => $cart->user?->email,
            'vang_lai' => $cart->user_id === null,
            'so_mon' => (int) $cart->items->sum('quantity'),
            'gia_tri' => $giaTri,
            'khong_dinh_gia' => $khongDinhGia,
            'lan_cuoi' => $lanCuoi,
            'so_ngay' => (int) floor($lanCuoi->diffInHours(now()) / 24),
            'mat_hang' => $matHang,
        ];
    }

    private function theoSanPham(Collection $gio): Collection
    {
        return $gio
            ->flatMap(fn ($g) => array_unique($g['mat_hang']))
            ->countBy()
            ->sortDesc()
            ->take(10)
            ->map(fn ($soGio, $ten) => ['ten' => (string) $ten, 'so_gio' => $soGio])
            ->values();
    }
}
