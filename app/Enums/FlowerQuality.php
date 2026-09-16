<?php

namespace App\Enums;

/** Chất lượng lô hoa khi nhận. */
enum FlowerQuality: string
{
    case Tot = 'tot';
    case TrungBinh = 'trung_binh';
    case Kem = 'kem';

    public function label(): string
    {
        return match ($this) {
            self::Tot => 'Tốt',
            self::TrungBinh => 'Trung bình',
            self::Kem => 'Kém',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Tot => 'success',
            self::TrungBinh => 'secondary',
            self::Kem => 'danger',
        };
    }
}
