<?php

namespace App\Enums;

/**
 * BỘ SƯU TẬP tự động — không phải danh mục, sản phẩm tự vào/ra theo giá và mùa.
 * Cố ý KHÔNG có bộ "hoa giá rẻ": khách muốn tiết kiệm thì tự kéo khoảng giá.
 */
enum FlowerCollection: string
{
    case TheoMua = 'theo-mua';
    case CaoCap = 'cao-cap';

    public function label(): string
    {
        return match ($this) {
            self::TheoMua => 'Hoa theo mùa',
            self::CaoCap => 'Hoa cao cấp',
        };
    }
}
