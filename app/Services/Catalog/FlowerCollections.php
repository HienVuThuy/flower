<?php

namespace App\Services\Catalog;

use App\Enums\FlowerCollection;
use App\Enums\FlowerSeason;
use App\Enums\GiftOccasion;
use App\Enums\ProductType;
use App\Enums\TraitType;
use App\Models\Product;
use App\Models\ProductTrait;
use App\Services\Time\Gio;
use Illuminate\Database\Eloquent\Builder;

/** Luật vào bộ sưu tập — một nơi, dùng chung cho trang danh sách, trang chủ và sitemap. */
class FlowerCollections
{
    public function apDung(Builder $query, FlowerCollection $bst): Builder
    {
        return match ($bst) {
            FlowerCollection::CaoCap => $query
                ->whereIn('product_type', [ProductType::Flower->value, ProductType::Artificial->value])
                ->effectivePriceBetween((int) config('catalog.cao_cap_tu'), null),

            FlowerCollection::TheoMua => $query->whereHas('traits', fn ($q) => $q
                ->where('trait_type', TraitType::Season->value)
                ->whereIn('trait_value', array_map(fn (FlowerSeason $m) => $m->value, $this->muaDangXet()))),
        };
    }

    /** Mùa này và mùa kế — hoa sắp vào mùa cũng đáng biết trước để đặt. */
    public function muaDangXet(): array
    {
        $nay = FlowerSeason::cua(now(Gio::mui()));

        return [$nay, $nay->ke()];
    }

    public function tieuDe(FlowerCollection $bst): string
    {
        if ($bst === FlowerCollection::TheoMua) {
            [$nay, $ke] = $this->muaDangXet();

            return 'Hoa theo mùa: ' . mb_strtolower($nay->label()) . ' và ' . mb_strtolower($ke->label());
        }

        return $bst->label();
    }

    public function moTa(FlowerCollection $bst): string
    {
        if ($bst === FlowerCollection::TheoMua) {
            [$nay, $ke] = $this->muaDangXet();

            return 'Hoa đang vào ' . mb_strtolower($nay->label()) . ' (' . $nay->thang() . ') và sắp vào '
                . mb_strtolower($ke->label()) . ' (' . $ke->thang() . '). Hoa đúng mùa tươi lâu và đẹp nhất trong năm.';
        }

        return 'Những thiết kế công phu, hoa tuyển chọn — dành cho dịp cần thật trang trọng.';
    }

    /** @return array<string, int> số sản phẩm đang bán trong từng bộ sưu tập, bỏ bộ trống */
    public function soLuong(): array
    {
        $out = [];

        foreach (FlowerCollection::cases() as $bst) {
            $n = $this->apDung(Product::query()->mainCatalog()->whereIn('status', ['active', 'out_of_stock']), $bst)->count();

            if ($n > 0) {
                $out[$bst->value] = $n;
            }
        }

        return $out;
    }

    /** @return array<string, int> số sản phẩm đang bán theo từng dịp tặng, bỏ dịp trống */
    public function soLuongTheoDip(): array
    {
        $dem = ProductTrait::query()
            ->where('trait_type', TraitType::Occasion->value)
            ->whereIn('product_id', Product::query()->mainCatalog()->whereIn('status', ['active', 'out_of_stock'])->select('id'))
            ->selectRaw('trait_value, count(*) as n')
            ->groupBy('trait_value')
            ->pluck('n', 'trait_value');

        $out = [];

        foreach (GiftOccasion::cases() as $dip) {
            if ((int) ($dem[$dip->value] ?? 0) > 0) {
                $out[$dip->value] = (int) $dem[$dip->value];
            }
        }

        return $out;
    }

    /**
     * Ngày lễ tặng hoa gần nhất trong $ngay ngày tới mà cửa hàng CÓ hàng hợp dịp.
     * Nhắc sớm để khách kịp đặt — không nhắc dịp mà bấm vào chỉ thấy trang trống.
     */
    public function dipSapToi(array $soLuongTheoDip, int $ngay = 30): ?array
    {
        $homNay = now(Gio::mui())->startOfDay();

        return collect(config('occasions', []))
            ->filter(fn (array $d) => ($d['dip'] ?? null) && ! ($d['lunar'] ?? false) && isset($soLuongTheoDip[$d['dip']]))
            ->map(function (array $d) use ($homNay) {
                $lan = $homNay->copy()->setDate($homNay->year, (int) $d['month'], (int) $d['day']);

                if ($lan->lt($homNay)) {
                    $lan->addYear();
                }

                return ['ten' => $d['name'], 'ngay' => $lan, 'con' => (int) $homNay->diffInDays($lan), 'dip' => GiftOccasion::from($d['dip'])];
            })
            ->filter(fn (array $d) => $d['con'] <= $ngay)
            ->sortBy('con')
            ->first();
    }
}
