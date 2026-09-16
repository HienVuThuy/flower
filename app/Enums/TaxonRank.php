<?php

namespace App\Enums;

/** Bậc phân loại sinh học: Giới → Ngành → Lớp → Bộ → Họ → Chi → Loài. */
enum TaxonRank: string
{
    case Kingdom = 'kingdom';
    case Phylum = 'phylum';
    case ClassRank = 'class';
    case Order = 'order';
    case Family = 'family';
    case Genus = 'genus';
    case Species = 'species';

    public function label(): string
    {
        return match ($this) {
            self::Kingdom => 'Giới',
            self::Phylum => 'Ngành',
            self::ClassRank => 'Lớp',
            self::Order => 'Bộ',
            self::Family => 'Họ',
            self::Genus => 'Chi',
            self::Species => 'Loài',
        };
    }

    public function level(): int
    {
        return match ($this) {
            self::Kingdom => 1,
            self::Phylum => 2,
            self::ClassRank => 3,
            self::Order => 4,
            self::Family => 5,
            self::Genus => 6,
            self::Species => 7,
        };
    }

    public function italic(): bool
    {
        return $this->level() >= self::Genus->level();
    }

    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
