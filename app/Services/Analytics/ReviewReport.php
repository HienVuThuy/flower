<?php

namespace App\Services\Analytics;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Support\Collection;

/**
 * Báo cáo đánh giá của khách.
 * ============================================================
 * THEO NGÀY VIẾT ĐÁNH GIÁ trong kỳ, gồm cả bài đang ẩn — cửa hàng ẩn một
 * đánh giá 1 sao không có nghĩa lời phàn nàn đó không tồn tại. Số bài ẩn
 * được đếm riêng và nói ra.
 *
 * TRUNG BÌNH LÀ NULL KHI KHÔNG CÓ BÀI NÀO. "0 sao" là một lời chê cụ thể;
 * "chưa có đánh giá" là chưa có gì để nói.
 */
class ReviewReport
{
    /** Sản phẩm cần ít nhất chừng này bài mới vào bảng "bị chê nhiều". */
    public const TOI_THIEU_BAI = 2;

    private KhoangThoiGian $khoang;

    public function __construct()
    {
        $this->khoang = new KhoangThoiGian();
    }

    public function trong(KhoangThoiGian $khoang): static
    {
        $this->khoang = $khoang;

        return $this;
    }

    /**
     * @return array{so_bai: int, trung_binh: float|null, phan_bo: array<int, int>, dang_an: int,
     *               thap_chua_tra_loi: int, ti_le_tra_loi_thap: float|null, co_don_hang: int,
     *               gio_tra_loi_trung_vi: float|null}
     */
    public function tongQuan(): array
    {
        $bai = $this->khoang->apDung(Review::query(), 'created_at')
            ->get(['rating', 'is_visible', 'admin_reply', 'admin_replied_at', 'order_id', 'created_at']);

        // Đủ 5 mức, kể cả mức bằng 0 — biểu đồ và bảng cùng một danh sách.
        $phanBo = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($bai as $b) {
            $phanBo[(int) $b->rating] = ($phanBo[(int) $b->rating] ?? 0) + 1;
        }

        $thap = $bai->filter(fn ($b) => (int) $b->rating <= 2);
        $thapDaTraLoi = $thap->filter(fn ($b) => filled($b->admin_reply));

        /*
         * THỜI GIAN TỪ LÚC KHÁCH VIẾT TỚI LÚC CỬA HÀNG TRẢ LỜI — TRUNG VỊ, không
         * phải trung bình. Một bài trả lời sau ba tháng kéo trung bình lên
         * hàng trăm giờ, trong khi mọi bài khác được trả lời trong ngày.
         */
        $soGio = $bai
            ->filter(fn ($b) => $b->admin_replied_at !== null)
            ->map(fn ($b) => $b->created_at->diffInMinutes($b->admin_replied_at) / 60)
            ->sort()
            ->values();

        return [
            'so_bai' => $bai->count(),
            'trung_binh' => $bai->isEmpty() ? null : round($bai->avg('rating'), 2),
            'phan_bo' => $phanBo,
            'dang_an' => $bai->where('is_visible', false)->count(),
            'thap_chua_tra_loi' => $thap->count() - $thapDaTraLoi->count(),
            'ti_le_tra_loi_thap' => $thap->isEmpty() ? null : round($thapDaTraLoi->count() / $thap->count() * 100, 1),
            'co_don_hang' => $bai->whereNotNull('order_id')->count(),
            'gio_tra_loi_trung_vi' => $this->trungVi($soGio),
        ];
    }

    /**
     * Sản phẩm bị chấm thấp nhất, ít nhất TOI_THIEU_BAI bài.
     *
     * Không có ngưỡng thì một sản phẩm với MỘT bài 2 sao đứng đầu bảng, trên
     * một sản phẩm với 30 bài trung bình 3,1 — sai cả thứ tự lẫn mức gấp.
     *
     * @return Collection<int, array{san_pham: ?Product, ten: string, so_bai: int, trung_binh: float, so_bai_thap: int}>
     */
    public function sanPhamBiCheNhieu(int $gioiHan = 10): Collection
    {
        $dong = $this->khoang->apDung(Review::query(), 'created_at')
            ->groupBy('product_id')
            ->havingRaw('COUNT(*) >= ?', [self::TOI_THIEU_BAI])
            ->selectRaw('product_id, COUNT(*) as so_bai, AVG(rating) as tb, SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) as thap')
            ->orderBy('tb')
            ->limit($gioiHan)
            ->get();

        $sp = Product::whereIn('id', $dong->pluck('product_id'))->get(['id', 'name', 'slug'])->keyBy('id');

        return $dong->map(fn ($d) => [
            'san_pham' => $sp->get($d->product_id),
            'ten' => $sp->get($d->product_id)?->name ?? '(sản phẩm đã xoá)',
            'so_bai' => (int) $d->so_bai,
            'trung_binh' => round((float) $d->tb, 2),
            'so_bai_thap' => (int) $d->thap,
        ]);
    }

    /**
     * Điểm trung bình theo THÁNG (giờ Việt Nam), để thấy chất lượng đang đi
     * lên hay xuống. Tháng không có bài thì không có dòng.
     *
     * @return Collection<int, array{thang: string, so_bai: int, trung_binh: float}>
     */
    public function theoThang(): Collection
    {
        return $this->khoang->apDung(Review::query(), 'created_at')
            ->orderBy('created_at')
            ->get(['rating', 'created_at'])
            ->groupBy(fn ($b) => KhoangThoiGian::diaPhuong($b->created_at)->format('Y-m'))
            ->map(fn (Collection $nhom, string $thang) => [
                'thang' => $thang,
                'so_bai' => $nhom->count(),
                'trung_binh' => round($nhom->avg('rating'), 2),
            ])
            ->values();
    }

    private function trungVi(Collection $so): ?float
    {
        $n = $so->count();

        if ($n === 0) {
            return null;
        }

        $giua = intdiv($n, 2);

        return round($n % 2 ? $so[$giua] : ($so[$giua - 1] + $so[$giua]) / 2, 1);
    }
}
