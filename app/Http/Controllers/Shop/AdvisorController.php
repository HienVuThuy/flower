<?php

namespace App\Http\Controllers\Shop;

use App\Enums\CareDifficulty;
use App\Enums\FengShuiElement;
use App\Enums\Placement;
use App\Enums\TraitType;
use App\Http\Controllers\Controller;
use App\Services\Recommendation\PlantAdvisor;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Trang "Chọn cây theo nhu cầu". */
class AdvisorController extends Controller
{
    public function __construct(
        private readonly PlantAdvisor $advisor,
    ) {
    }

    public function index(Request $request): View
    {
        $placement = Placement::tryFrom((string) $request->query('vi-tri', ''));
        $element = FengShuiElement::tryFrom((string) $request->query('menh', ''));
        $difficulty = CareDifficulty::tryFrom((string) $request->query('kinh-nghiem', ''));

        $loaiSinhThai = [
            TraitType::Habitat,
            TraitType::GrowthForm,
            TraitType::Shape,
            TraitType::Color,
        ];

        $traits = [];

        foreach ($loaiSinhThai as $type) {
            $value = (string) $request->query($type->queryKey(), '');

            if ($value !== '' && array_key_exists($value, $type->options())) {
                $traits[$type->value] = $value;
            }
        }

        $hasFilter = $placement !== null || $element !== null || $difficulty !== null || $traits !== [];

        return view('shop.advisor.index', [
            'placement' => $placement,
            'element' => $element,
            'difficulty' => $difficulty,
            'hasFilter' => $hasFilter,

            'placements' => $this->advisor->availablePlacements(),
            'elements' => $this->advisor->availableElements(),
            'difficulties' => $this->advisor->availableDifficulties(),

            'nhomSinhThai' => collect($loaiSinhThai)
                ->map(fn (TraitType $t) => [
                    'type' => $t,
                    'values' => $this->advisor->availableTraitValues($t),
                    'dangChon' => $traits[$t->value] ?? null,
                ])
                ->filter(fn (array $n) => $n['values']->isNotEmpty()),

            'ketQuaUrl' => route('shop.products.index', array_filter([
                'vi-tri' => $placement?->value,
                'menh' => $element?->value,
                'kinh-nghiem' => $difficulty?->value,
            ] + $this->thamSoNhan($traits))),
        ]);
    }

    private function thamSoNhan(array $traits): array
    {
        $ket = [];

        foreach ($traits as $loai => $giaTri) {
            $type = TraitType::tryFrom((string) $loai);

            if ($type) {
                $ket[$type->queryKey()] = $giaTri;
            }
        }

        return $ket;
    }
}
