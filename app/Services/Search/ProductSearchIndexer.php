<?php

namespace App\Services\Search;

use App\Enums\TraitType;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Dựng nội dung hai cột products.search_name / products.search_text. */
class ProductSearchIndexer
{
    public const VERSION_KEY = 'search.dictionary.version';

    public function __construct(
        private readonly TextNormalizer $normalizer,
    ) {
    }

    public function values(Product $product): array
    {
        $name = $this->normalizer->normalize($product->name);

        $parts = [
            $product->name,
            $product->product_code,
            $product->short_description,
            $product->category?->name,
            $product->selling_form?->label(),
            $product->product_type?->label(),
            ...$this->nhanDipVaMua($product),
        ];

        $text = $this->normalizer->normalize(implode(' ', array_filter($parts)));

        return [
            'search_name' => mb_substr($name, 0, 255),
            'search_text' => mb_substr($this->dedupe($text), 0, 1000),
        ];
    }

    /** "hoa sinh nhật", "hoa tình yêu", "hoa mùa xuân" phải tìm ra đúng hàng đã gắn nhãn. */
    private function nhanDipVaMua(Product $product): array
    {
        if (! $product->exists) {
            return [];
        }

        $out = [];

        foreach ([TraitType::Occasion, TraitType::Season] as $loai) {
            foreach ($product->traitValues($loai) as $giaTri) {
                $out[] = 'hoa ' . $loai->labelFor($giaTri);
            }
        }

        return $out;
    }

    public function fill(Product $product): void
    {
        if ($product->category_id && ! $product->relationLoaded('category')) {
            $product->load('category');
        }

        foreach ($this->values($product) as $column => $value) {
            $product->setAttribute($column, $value);
        }
    }

    public function reindexAll(): array
    {
        $written = 0;
        $scanned = 0;

        Product::query()
            ->withTrashed()
            ->with(['category', 'traits'])
            ->chunkById(200, function ($products) use (&$written, &$scanned) {
                foreach ($products as $product) {
                    $scanned++;
                    $values = $this->values($product);

                    if ($product->search_name === $values['search_name']
                        && $product->search_text === $values['search_text']) {
                        continue;
                    }

                    DB::table('products')->where('id', $product->id)->update($values);
                    $written++;
                }
            });

        $this->bumpVersion();

        return ['scanned' => $scanned, 'written' => $written];
    }

    public function reindexCategory(Category $category): int
    {
        $written = 0;

        Product::query()
            ->withTrashed()
            ->where('category_id', $category->id)
            ->chunkById(200, function ($products) use (&$written, $category) {
                foreach ($products as $product) {
                    $product->setRelation('category', $category);
                    DB::table('products')->where('id', $product->id)->update($this->values($product));
                    $written++;
                }
            });

        $this->bumpVersion();

        return $written;
    }

    public function bumpVersion(): void
    {
        Cache::add(self::VERSION_KEY, 1);
        Cache::increment(self::VERSION_KEY);
    }

    private function dedupe(string $text): string
    {
        if ($text === '') {
            return '';
        }

        return implode(' ', array_keys(array_flip(explode(' ', $text))));
    }
}
