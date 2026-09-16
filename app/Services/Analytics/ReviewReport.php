<?php

namespace App\Services\Analytics;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Support\Collection;

/** Báo cáo đánh giá của khách. */
class ReviewReport
{
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

    public function tongQuan(): array
    {
        $bai = $this->khoang->apDung(Review::query(), 'created_at')
            ->get(['rating', 'is_visible', 'admin_reply', 'admin_replied_at', 'order_id', 'created_at']);

        $phanBo = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($bai as $b) {
            $phanBo[(int) $b->rating] = ($phanBo[(int) $b->rating] ?? 0) + 1;
        }

        $thap = $bai->filter(fn ($b) => (int) $b->rating <= 2);
        $thapDaTraLoi = $thap->filter(fn ($b) => filled($b->admin_reply));

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
