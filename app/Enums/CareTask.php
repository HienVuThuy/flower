<?php

namespace App\Enums;

/**
 * Việc chăm sóc định kỳ có thể nhắc lịch được.
 * ============================================================
 * CHỈ HAI VIỆC, và cố ý không thêm.
 *
 * Tưới nước và bón phân là hai việc có CHU KỲ CỐ ĐỊNH, đo được bằng số
 * ngày, và bỏ quên thì cây chết. Những việc khác trong `care_info` — ánh
 * sáng, đất trồng, nhiệt độ — là điều kiện chứ không phải hành động lặp
 * lại, nhắc theo lịch không có nghĩa gì.
 *
 * Mỗi case gắn với đúng một khoá trong care_info (`configKey`), nên thêm
 * một việc mới sau này chỉ cần thêm một case và một ô trong CareProfile.
 */
enum CareTask: string
{
    case Water = 'water';
    case Fertilizer = 'fertilizer';

    /** Khoá trong care_info chứa số ngày của chu kỳ. */
    public function configKey(): string
    {
        return match ($this) {
            self::Water => 'water_days',
            self::Fertilizer => 'fertilizer_days',
        };
    }

    /** Khoá trong care_info chứa lời khuyên viết cho người đọc. */
    public function adviceKey(): string
    {
        return match ($this) {
            self::Water => 'water',
            self::Fertilizer => 'fertilizer',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Water => 'Tưới nước',
            self::Fertilizer => 'Bón phân',
        };
    }

    /** Câu mở đầu của thư nhắc. */
    public function headline(): string
    {
        return match ($this) {
            self::Water => 'Đến lúc tưới cây rồi',
            self::Fertilizer => 'Đến kỳ bón phân',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Water => 'droplet',
            self::Fertilizer => 'moisture',
        };
    }
}
