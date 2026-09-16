<?php

namespace App\Services\Promotion;

use App\Models\Promotion;

/** Cung cấp chương trình khuyến mại đang chạy cho các khối giao diện (thanh thông báo, banner chiến dịch ở… */
class ActivePromotionProvider
{
    private bool $resolved = false;

    private ?Promotion $promotion = null;

    private ?\Illuminate\Support\Collection $dangChay = null;

    public function featured(): ?Promotion
    {
        if ($this->resolved) {
            return $this->promotion;
        }

        $this->resolved = true;

        $this->promotion = $this->dangChay()->first();

        return $this->promotion;
    }

    public function dangChay(int $toiDa = 3): \Illuminate\Support\Collection
    {
        $this->dangChay ??= Promotion::query()
            ->activeNow()
            ->withCount('products')
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->filter(fn (Promotion $km) => $km->products_count > 0 && $km->isRunning())
            ->values();

        return $this->dangChay->take($toiDa)->values();
    }
}
