<?php

namespace App\Services\Search;

use Illuminate\Database\Eloquent\Builder;

/** Điều phối việc tìm sản phẩm: chuẩn hoá -> sửa lỗi gõ -> lọc -> xếp hạng. */
class ProductSearch
{
    private const ALTERNATIVE_WEIGHT_FACTOR = 2;

    public function __construct(
        private readonly TextNormalizer $normalizer,
        private readonly SearchDictionary $dictionary,
        private readonly FuzzyMatcher $matcher,
    ) {
    }

    public function terms(?string $raw): SearchTerms
    {
        $original = trim((string) $raw);
        $tokens = $this->normalizer->tokenize($original);

        if ($tokens === []) {
            return SearchTerms::empty($original);
        }

        $final = [];
        $corrections = [];

        foreach ($tokens as $token) {
            if ($this->dictionary->matches($token)) {
                $final[$token] = true;

                continue;
            }

            $fixed = $this->matcher->closest($token, $this->dictionary->words());

            if ($fixed === null) {
                $final[$token] = true;

                continue;
            }

            $final[$fixed] = true;
            $corrections[$token] = $fixed;
        }

        $tokens = array_keys($final);

        return new SearchTerms(
            $original,
            $tokens,
            $corrections,
            $corrections === [] && count($tokens) === 1
                ? $this->alternativeFor($tokens[0])
                : null,
        );
    }

    private function alternativeFor(string $token): ?string
    {
        $words = $this->dictionary->words();

        foreach ($words as $word => $weight) {
            if (strlen((string) $word) > strlen($token) && str_starts_with((string) $word, $token)) {
                return null;
            }
        }

        $baseWeight = $words[$token] ?? 0;

        $others = array_filter(
            $words,
            fn ($word) => ! str_starts_with((string) $word, $token),
            ARRAY_FILTER_USE_KEY,
        );

        $best = $this->matcher->closest($token, $others);

        if ($best === null) {
            return null;
        }

        return $words[$best] > $baseWeight * self::ALTERNATIVE_WEIGHT_FACTOR
            ? $best
            : null;
    }

    public function filter(Builder $query, SearchTerms $terms, bool $matchAll = true): void
    {
        if ($terms->isEmpty()) {
            return;
        }

        $query->where(function (Builder $group) use ($terms, $matchAll) {
            foreach ($terms->tokens as $index => $token) {
                $condition = fn (Builder $q) => $this->matchToken($q, 'search_text', $token);

                if ($matchAll || $index === 0) {
                    $group->where($condition);
                } else {
                    $group->orWhere($condition);
                }
            }
        });
    }

    private function matchToken(Builder $query, string $column, string $token): Builder
    {
        $escaped = $this->escapeLike($token);

        return $query
            ->where($column, 'like', $escaped.'%')
            ->orWhere($column, 'like', '% '.$escaped.'%');
    }

    public function orderByRelevance(Builder $query, SearchTerms $terms): void
    {
        if ($terms->isEmpty()) {
            return;
        }

        $phrase = $terms->suggestion();

        $sql = [];
        $bindings = [];

        $sql[] = 'CASE WHEN search_name = ? THEN 100 ELSE 0 END';
        $bindings[] = $phrase;

        $sql[] = 'CASE WHEN search_name LIKE ? THEN 50 ELSE 0 END';
        $bindings[] = $this->escapeLike($phrase).'%';

        $sql[] = 'CASE WHEN search_text LIKE ? THEN 25 ELSE 0 END';
        $bindings[] = '%'.$this->escapeLike($phrase).'%';

        foreach ($terms->tokens as $token) {
            $escaped = $this->escapeLike($token);
            $sql[] = 'CASE WHEN search_name LIKE ? OR search_name LIKE ? THEN 10 ELSE 0 END';
            $bindings[] = $escaped.'%';
            $bindings[] = '% '.$escaped.'%';
        }

        $query->orderByRaw('('.implode(' + ', $sql).') DESC', $bindings);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
