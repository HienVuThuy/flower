<?php

namespace App\Services\Promotion;

use App\Enums\PromotionStatus;
use App\Models\Promotion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Dịp lễ sắp tới, và dịp nào chưa có chương trình nào phủ.
 * ============================================================
 * CHỈ NHẮC, KHÔNG TỰ TẠO.
 *
 * Lớp này không tạo chương trình khuyến mại. Tạo chương trình là quyết
 * định giá bán, và quyết định giá phải có người bấm nút — không phải một
 * tác vụ nền chạy lúc nửa đêm rồi sáng ra cả cửa hàng giảm 20%.
 *
 * ============================================================
 * "ĐÃ PHỦ" NGHĨA LÀ GÌ.
 *
 * Một dịp được coi là đã phủ khi có ít nhất một chương trình còn hiệu
 * lực (Đang chạy hoặc Đã lên lịch) mà khoảng ngày của nó CHỨA ngày diễn
 * ra dịp đó.
 *
 * Chương trình không đặt ngày kết thúc thì phủ mọi dịp từ ngày bắt đầu
 * trở đi — đúng như cách `Promotion::scopeActiveNow()` hiểu "để trống là
 * không giới hạn". Hai chỗ phải hiểu giống nhau, nếu không admin sẽ thấy
 * một chương trình đang chạy mà công cụ vẫn báo "chưa có gì".
 *
 * ============================================================
 * DỊP ÂM LỊCH KHÔNG ĐƯỢC ĐOÁN NGÀY DƯƠNG.
 *
 * Xem chú thích ở `config/occasions.php`. Chúng được trả về trong một
 * danh sách RIÊNG, không có ngày, không có kết luận "đã phủ hay chưa" —
 * vì không biết ngày thì không kiểm được. Nói "chưa có chương trình cho
 * Tết" khi admin đã tạo một chương trình Tết là một cảnh báo sai, và vài
 * lần như thế là admin ngừng đọc cả khối này.
 */
class OccasionCalendar
{
    /**
     * Các dịp DƯƠNG LỊCH sắp tới trong `$days` ngày, kèm tình trạng phủ.
     *
     * @return Collection<int, array{
     *   key: string, name: string, date: Carbon, days_away: int,
     *   weight: int, note: ?string, covered_by: ?Promotion,
     * }>
     */
    public function upcoming(int $days = 60): Collection
    {
        $homNay = now()->startOfDay();
        $den = $homNay->copy()->addDays($days);

        $chuongTrinh = $this->chuongTrinhConHieuLuc();

        return collect(config('occasions', []))
            ->filter(fn (array $d) => ! ($d['lunar'] ?? false) && $d['day'] && $d['month'])
            ->map(function (array $d) use ($homNay) {
                $ngay = $this->lanToiCua((int) $d['day'], (int) $d['month'], $homNay);

                return [
                    'key' => $d['key'],
                    'name' => $d['name'],
                    'date' => $ngay,
                    'days_away' => (int) $homNay->diffInDays($ngay, false),
                    'weight' => (int) ($d['weight'] ?? 5),
                    'note' => $d['note'] ?? null,
                ];
            })
            ->filter(fn (array $d) => $d['date']->lte($den))
            ->map(function (array $d) use ($chuongTrinh) {
                $d['covered_by'] = $chuongTrinh->first(
                    fn (Promotion $p) => $this->phu($p, $d['date']),
                );

                return $d;
            })
            ->sortBy([
                fn (array $a, array $b) => $a['date']->timestamp <=> $b['date']->timestamp,
            ])
            ->values();
    }

    /**
     * Dịp âm lịch — liệt kê để nhắc, KHÔNG kèm ngày và KHÔNG kết luận.
     *
     * @return Collection<int, array{name: string, lunar_note: string, note: ?string}>
     */
    public function lunar(): Collection
    {
        return collect(config('occasions', []))
            ->filter(fn (array $d) => $d['lunar'] ?? false)
            ->sortBy(fn (array $d) => $d['weight'] ?? 5)
            ->map(fn (array $d) => [
                'name' => $d['name'],
                'lunar_note' => $d['lunar_note'] ?? 'theo âm lịch',
                'note' => $d['note'] ?? null,
            ])
            ->values();
    }

    /* ================= NỘI BỘ ================= */

    /**
     * Lần tới của một ngày trong năm.
     *
     * Đã qua trong năm nay thì lấy năm sau — nhờ vậy tháng 12 vẫn nhìn
     * thấy Valentine và Tết Dương lịch của năm kế tiếp, đúng lúc cần
     * chuẩn bị nhất.
     */
    private function lanToiCua(int $ngay, int $thang, Carbon $homNay): Carbon
    {
        $trongNam = Carbon::create($homNay->year, $thang, $ngay)->startOfDay();

        return $trongNam->gte($homNay)
            ? $trongNam
            : Carbon::create($homNay->year + 1, $thang, $ngay)->startOfDay();
    }

    /** @return Collection<int, Promotion> */
    private function chuongTrinhConHieuLuc(): Collection
    {
        return Promotion::query()
            ->where('status', PromotionStatus::Active)
            /*
             * Bỏ những chương trình đã kết thúc hẳn. Chương trình chưa
             * tới ngày thì GIỮ — nó chính là thứ chứng minh dịp đó đã
             * được chuẩn bị.
             */
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()->startOfDay()))
            ->orderBy('starts_at')
            ->get();
    }

    private function phu(Promotion $p, Carbon $ngay): bool
    {
        if ($p->starts_at && $p->starts_at->startOfDay()->gt($ngay)) {
            return false;
        }

        if ($p->ends_at && $p->ends_at->startOfDay()->lt($ngay)) {
            return false;
        }

        return true;
    }
}
