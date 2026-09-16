<?php

namespace App\Services\Search;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/** Từ điển các từ THẬT SỰ có trong catalog. */
class SearchDictionary
{
    private const TTL_SECONDS = 3600;

    private const MIN_WORD_LENGTH = 2;

    private ?array $memo = null;

    public function words(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $version = Cache::get(ProductSearchIndexer::VERSION_KEY, 1);

        return $this->memo = Cache::remember(
            "search.dictionary.v{$version}",
            self::TTL_SECONDS,
            fn () => $this->build(),
        );
    }

    public function matches(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        foreach ($this->words() as $word => $_) {
            if (str_starts_with((string) $word, $token)) {
                return true;
            }
        }

        return false;
    }

    public function flush(): void
    {
        $this->memo = null;
    }

    private function build(): array
    {
        $counts = [];

        Product::query()
            ->whereIn('status', ['active', 'out_of_stock'])
            ->whereNotNull('search_text')
            ->select(['id', 'search_text'])
            ->chunkById(500, function ($products) use (&$counts) {
                foreach ($products as $product) {
                    foreach (array_flip(explode(' ', $product->search_text)) as $word => $_) {
                        $word = (string) $word;

                        if (strlen($word) >= self::MIN_WORD_LENGTH) {
                            $counts[$word] = ($counts[$word] ?? 0) + 1;
                        }
                    }
                }
            });

        arsort($counts);

        return $counts;
    }
}
