<?php

namespace App\Services\Loyalty;

use App\Models\MemberTier;
use App\Models\User;
use Illuminate\Support\Collection;

/** Khách này đang ở hạng nào, và còn bao xa hạng kế tiếp. */
class MemberTierResolver
{
    public function __construct(
        private readonly QualifiedSpending $chiTieu,
    ) {
    }

    public function tatCa(): Collection
    {
        return MemberTier::query()->orderBy('min_spend')->get();
    }

    public function theoChiTieu(string $tien, ?Collection $tatCa = null): ?MemberTier
    {
        return ($tatCa ?? $this->tatCa())
            ->filter(fn (MemberTier $h) => bccomp($tien, (string) $h->min_spend, 2) >= 0)
            ->last();
    }

    public function cua(User $user): array
    {
        $tatCa = $this->tatCa();
        $tien = $this->chiTieu->cua($user);
        $keTiep = $tatCa->first(fn (MemberTier $h) => bccomp((string) $h->min_spend, $tien, 2) > 0);

        return [
            'hang' => $this->theoChiTieu($tien, $tatCa),
            'chi_tieu' => $tien,
            'ke_tiep' => $keTiep,
            'con_thieu' => $keTiep ? bcsub((string) $keTiep->min_spend, $tien, 2) : null,
        ];
    }
}
