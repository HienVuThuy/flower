<?php

namespace App\Services\Promotion;

use App\Enums\PromotionStatus;
use App\Models\Promotion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Dịp lễ sắp tới, và dịp nào chưa có chương trình nào phủ. */
class OccasionCalendar
{
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

    private function lanToiCua(int $ngay, int $thang, Carbon $homNay): Carbon
    {
        $trongNam = Carbon::create($homNay->year, $thang, $ngay)->startOfDay();

        return $trongNam->gte($homNay)
            ? $trongNam
            : Carbon::create($homNay->year + 1, $thang, $ngay)->startOfDay();
    }

    private function chuongTrinhConHieuLuc(): Collection
    {
        return Promotion::query()
            ->where('status', PromotionStatus::Active)
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
