<?php

namespace App\Services\Recommendation;

use App\Enums\TaxonRank;
use App\Enums\TraitType;
use App\Models\PlantTaxon;
use App\Models\Product;
use App\Models\ProductTrait;
use Illuminate\Support\Collection;

/**
 * Chân dung sở thích của một người xem, dựng từ hành vi công khai.
 * ⚠️ RANH GIỚI RIÊNG TƯ — ĐỌC TRƯỚC KHI SỬA.
 */
class TasteProfile
{
    private const WEIGHTS = [
        'purchase' => 5,
        'wishlist' => 4,
        'add_to_cart' => 3,
        'product_view' => 1,
        'category_view' => 1,
        'search' => 0,
    ];

    private const AXIS_WEIGHTS = [
        'category' => 3.0,
        'trait' => 2.0,
        'form' => 2.0,
        'taxon' => 1.0,
    ];

    private const BAC_TOI_THIEU = TaxonRank::Family;

    public readonly Collection $categoryScores;

    public readonly Collection $formScores;

    public readonly Collection $traitScores;

    public readonly Collection $taxonScores;

    public function __construct(Collection $history)
    {
        $this->categoryScores = $this->diemTheoCot($history, 'category_id');
        $this->formScores = $this->diemTheoCot($history, 'selling_form');
        $this->traitScores = $this->diemTheoNhan($history);
        $this->taxonScores = $this->diemTheoPhanLoai($history);
    }

    public function isEmpty(): bool
    {
        return $this->categoryScores->isEmpty()
            && $this->formScores->isEmpty()
            && $this->traitScores->isEmpty()
            && $this->taxonScores->isEmpty();
    }

    public function match(Product $product): array
    {
        $truc = [
            'category' => $this->hopDanhMuc($product),
            'form' => $this->hopHinhThuc($product),
            'trait' => $this->hopDacDiem($product),
            'taxon' => $this->hopPhanLoai($product),
        ];

        $diem = 0.0;

        foreach ($truc as $ten => ['affinity' => $doHop]) {
            $diem += self::AXIS_WEIGHTS[$ten] * $doHop;
        }

        return [
            'score' => $diem,
            'reason' => $this->lyDo($truc),
        ];
    }

    private function lyDo(array $truc): ?string
    {
        foreach (['taxon', 'trait', 'form', 'category'] as $ten) {
            $t = $truc[$ten];

            if ($t['reason'] !== null && $t['affinity'] >= self::NGUONG_LY_DO) {
                return $t['reason'];
            }
        }

        foreach (['taxon', 'trait', 'form', 'category'] as $ten) {
            if ($truc[$ten]['reason'] !== null && $truc[$ten]['affinity'] > 0) {
                return $truc[$ten]['reason'];
            }
        }

        return null;
    }

    private const NGUONG_LY_DO = 0.5;

    private function hopDanhMuc(Product $product): array
    {
        $diem = (int) ($this->categoryScores[$product->category_id] ?? 0);

        if ($diem <= 0) {
            return ['affinity' => 0.0, 'reason' => null];
        }

        return [
            'affinity' => $diem / (int) $this->categoryScores->max(),
            'reason' => $product->relationLoaded('category') && $product->category
                ? 'Vì bạn quan tâm ' . $product->category->name
                : null,
        ];
    }

    private function hopHinhThuc(Product $product): array
    {
        $form = $product->selling_form?->value;
        $diem = $form ? (int) ($this->formScores[$form] ?? 0) : 0;

        if ($diem <= 0) {
            return ['affinity' => 0.0, 'reason' => null];
        }

        return [
            'affinity' => $diem / (int) $this->formScores->max(),
            'reason' => 'Cùng hình thức ' . ($product->selling_form?->label() ?? 'bán'),
        ];
    }

    private function hopDacDiem(Product $product): array
    {
        if ($this->traitScores->isEmpty() || ! $product->relationLoaded('traits')) {
            return ['affinity' => 0.0, 'reason' => null];
        }

        $khop = [];

        foreach ($product->traits as $nhan) {
            if ($nhan->trait_type === TraitType::AccessoryFor) {
                continue;
            }

            $khoa = $this->khoaNhan($nhan->trait_type, $nhan->trait_value);
            $diem = (int) ($this->traitScores[$khoa] ?? 0);

            if ($diem > 0) {
                $khop[$khoa] = ['diem' => $diem, 'nhan' => $nhan];
            }
        }

        if ($khop === []) {
            return ['affinity' => 0.0, 'reason' => null];
        }

        $tong = (int) $this->traitScores->sum();
        $duoc = array_sum(array_column($khop, 'diem'));

        $manh = collect($khop)->sortByDesc('diem')->first()['nhan'];

        return [
            'affinity' => min(1.0, $duoc / max(1, $tong)),
            'reason' => $this->lyDoNhan($manh->trait_type, $manh->label()),
        ];
    }

    private function hopPhanLoai(Product $product): array
    {
        if ($this->taxonScores->isEmpty() || ! $product->taxon_id) {
            return ['affinity' => 0.0, 'reason' => null];
        }

        $chuoi = $this->chuoiToTien($product->taxon_id);

        foreach ($chuoi->reverse() as $nut) {
            if ($nut->rank->level() < self::BAC_TOI_THIEU->level()) {
                break;
            }

            $diem = (int) ($this->taxonScores[$nut->id] ?? 0);

            if ($diem > 0) {
                return [
                    'affinity' => $diem / (int) $this->taxonScores->max(),
                    'reason' => 'Cùng ' . mb_strtolower($nut->rank->label()) . ' ' . $nut->name,
                ];
            }
        }

        return ['affinity' => 0.0, 'reason' => null];
    }

    private function diemTheoCot(Collection $history, string $cot): Collection
    {
        return $history
            ->filter(fn ($e) => ! empty($e->{$cot}))
            ->groupBy(fn ($e) => (string) $e->{$cot})
            ->map(fn (Collection $rows) => $rows->sum(fn ($e) => $this->trongSo($e->event_type)))
            ->filter(fn (int $diem) => $diem > 0)
            ->sortDesc();
    }

    private function diemTheoNhan(Collection $history): Collection
    {
        $diemSanPham = $history
            ->filter(fn ($e) => ! empty($e->product_id))
            ->groupBy(fn ($e) => (int) $e->product_id)
            ->map(fn (Collection $rows) => $rows->sum(fn ($e) => $this->trongSo($e->event_type)))
            ->filter(fn (int $d) => $d > 0);

        if ($diemSanPham->isEmpty()) {
            return collect();
        }

        $nhan = ProductTrait::query()
            ->whereIn('product_id', $diemSanPham->keys()->all())
            ->where('trait_type', '!=', TraitType::AccessoryFor->value)
            ->get(['product_id', 'trait_type', 'trait_value']);

        $ket = [];

        foreach ($nhan as $n) {
            $khoa = $this->khoaNhan($n->trait_type, $n->trait_value);
            $ket[$khoa] = ($ket[$khoa] ?? 0) + (int) $diemSanPham[(int) $n->product_id];
        }

        return collect($ket)->sortDesc();
    }

    private function diemTheoPhanLoai(Collection $history): Collection
    {
        $diemNut = $history
            ->filter(fn ($e) => ! empty($e->taxon_id))
            ->groupBy(fn ($e) => (int) $e->taxon_id)
            ->map(fn (Collection $rows) => $rows->sum(fn ($e) => $this->trongSo($e->event_type)))
            ->filter(fn (int $d) => $d > 0);

        if ($diemNut->isEmpty()) {
            return collect();
        }

        $ket = [];

        foreach ($diemNut as $taxonId => $diem) {
            foreach ($this->chuoiToTien((int) $taxonId) as $nut) {
                if ($nut->rank->level() < self::BAC_TOI_THIEU->level()) {
                    continue;
                }

                $ket[$nut->id] = ($ket[$nut->id] ?? 0) + (int) $diem;
            }
        }

        return collect($ket)->sortDesc();
    }

    private ?Collection $cayPhanLoai = null;

    private function chuoiToTien(int $taxonId): Collection
    {
        $this->cayPhanLoai ??= PlantTaxon::query()
            ->get(['id', 'parent_id', 'rank', 'name'])
            ->keyBy('id');

        $chuoi = collect();
        $nut = $this->cayPhanLoai->get($taxonId);

        $conLai = count(TaxonRank::cases());

        while ($nut && $conLai-- > 0) {
            $chuoi->prepend($nut);
            $nut = $nut->parent_id ? $this->cayPhanLoai->get($nut->parent_id) : null;
        }

        return $chuoi;
    }

    private function khoaNhan(TraitType $type, string $value): string
    {
        return $type->value . ':' . $value;
    }

    private function lyDoNhan(TraitType $type, string $nhan): string
    {
        return match ($type) {
            TraitType::Color => 'Cũng tông màu ' . mb_strtolower($nhan),
            TraitType::Shape => 'Cùng dáng ' . mb_strtolower($nhan),
            TraitType::GrowthForm => 'Cùng dạng ' . mb_strtolower($nhan),
            TraitType::Habitat => 'Cùng môi trường sống: ' . $nhan,
            TraitType::Placement => 'Cũng hợp đặt ' . mb_strtolower($nhan),
            TraitType::FengShui => 'Cũng hợp mệnh ' . $nhan,
            TraitType::AccessoryFor => 'Dùng kèm ' . mb_strtolower($nhan),
            TraitType::Occasion => 'Cũng hợp dịp ' . mb_strtolower($nhan),
            TraitType::Season => 'Cũng nở vào ' . mb_strtolower($nhan),
        };
    }

    private function trongSo(mixed $eventType): int
    {
        $khoa = $eventType instanceof \App\Enums\UserEventType
            ? $eventType->value
            : (string) $eventType;

        return self::WEIGHTS[$khoa] ?? 0;
    }
}
