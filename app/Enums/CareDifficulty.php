<?php

namespace App\Enums;

/** Độ khó chăm sóc của một cây. */
enum CareDifficulty: string
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    public function label(): string
    {
        return match ($this) {
            self::Easy => 'Dễ',
            self::Medium => 'Trung bình',
            self::Hard => 'Khó',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Easy => 'Chịu được quên tưới vài ngày',
            self::Medium => 'Cần tưới và để đúng chỗ đều đặn',
            self::Hard => 'Cần theo dõi thường xuyên',
        };
    }

    public function experienceLabel(): string
    {
        return match ($this) {
            self::Easy => 'Tôi mới trồng cây',
            self::Medium => 'Đã trồng được vài cây',
            self::Hard => 'Muốn thử cây khó hơn',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
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
