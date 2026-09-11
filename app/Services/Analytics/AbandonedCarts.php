<?php

namespace App\Services\Analytics;

use App\Models\Cart;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Giỏ hàng bỏ dở: khách đã chọn hàng mà không đặt.
 * ============================================================
 * ĐỊNH NGHĨA, và vì sao mỗi vế cần:
 *
 *   1. Giỏ CÒN HÀNG. Giỏ rỗng không bỏ dở gì cả — bảng `carts` có 47 dòng
 *      nhưng chỉ 3 giỏ còn hàng; đếm cả 47 là bảo cửa hàng mất 47 khách.
 *
 *   2. KHÔNG ĐỘNG TỚI QUÁ 24 GIỜ, tính theo lần sửa gần nhất của GIỎ HOẶC
 *      BẤT KỲ DÒNG NÀO. Người vừa bỏ hàng vào giỏ 10 phút trước đang mua,
 *      không phải đang bỏ.
 *
 *   3. KHÁCH CÓ TÀI KHOẢN mà đã đặt một đơn SAU lần động tới cuối thì không
 *      tính: họ đã mua, phần còn trong giỏ là món họ chọn không mua.
 *      Khách vãng lai không nối được giỏ với đơn (đơn không lưu phiên),
 *      nên KHÔNG loại được — nói ra trên giao diện.
 *
 * GIÁ TRỊ TÍNH THEO GIÁ HIỆN TẠI, qua CartItem::unitPrice() — cùng hàm trang
 * giỏ hàng dùng. Giỏ không chụp giá lúc bỏ vào; con số này trả lời "nếu họ
 * quay lại mua bây giờ thì được bao nhiêu", và giao diện gọi đúng tên như vậy.
 *
 * KHÔNG THEO KỲ ĐANG CHỌN: đây là tình trạng HIỆN TẠI, không phải chuyện đã
 * xảy ra trong một khoảng. Chia theo độ lâu thay vì theo kỳ.
 */
class AbandonedCarts
{
    public const BO_SAU_GIO = 24;

    /**
     * @return array{
     *     gio: Collection<int, array{khach: string, email: ?string, vang_lai: bool, so_mon: int,
     *                                 gia_tri: string, khong_dinh_gia: int, lan_cuoi: Carbon, so_ngay: int,
     *                                 mat_hang: list<string>}>,
     *     tong_gia_tri: string, so_gio: int, so_mon: int, vang_lai: int,
     *     theo_do_lau: array<string, int>,
     *     theo_san_pham: Collection<int, array{ten: string, so_gio: int, so_luong: int}>
     * }
     */
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

            /*
             * GIÁ LIÊN HỆ: sản phẩm không có giá niêm yết (hoa sự kiện, cây cỡ
             * lớn). Không cộng như 0₫ — đếm riêng để giao diện nói "chưa tính
             * được N món".
             */
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

    /** Sản phẩm nằm lại trong nhiều giỏ bỏ dở nhất. */
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
