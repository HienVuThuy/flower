<?php

namespace App\Services\Search;

/** Tìm từ gần đúng nhất trong từ điển cho một từ khoá gõ sai. */
class FuzzyMatcher
{
    private const THRESHOLDS = [
        1 => 0,
        2 => 1,
        3 => 1,
        4 => 1,
        5 => 2,
        6 => 2,
        7 => 2,
    ];

    private const THRESHOLD_LONG = 3;

    private const SHORT_WORD_LENGTH = 5;

    public function maxDistance(string $token): int
    {
        return self::THRESHOLDS[strlen($token)] ?? self::THRESHOLD_LONG;
    }

    public function closest(string $token, array $dictionary): ?string
    {
        $max = $this->maxDistance($token);

        if ($max === 0 || $token === '') {
            return null;
        }

        $len = strlen($token);
        $requireSameFirst = $len < self::SHORT_WORD_LENGTH;
        $first = $token[0];

        $best = null;
        $bestDistance = PHP_INT_MAX;
        $bestWeight = -1;

        foreach ($dictionary as $word => $weight) {
            $word = (string) $word;

            if (abs(strlen($word) - $len) > $max) {
                continue;
            }

            if ($requireSameFirst && $word[0] !== $first) {
                continue;
            }

            $distance = levenshtein($token, $word);

            if ($distance > $max) {
                continue;
            }

            $better = $distance < $bestDistance
                || ($distance === $bestDistance && $weight > $bestWeight)
                || ($distance === $bestDistance && $weight === $bestWeight && $best !== null && $word < $best);

            if ($better) {
                $best = $word;
                $bestDistance = $distance;
                $bestWeight = $weight;
            }
        }

        return $best;
    }
}
