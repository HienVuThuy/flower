<?php

namespace App\Enums;

/** Quà còn ít hơn số được tặng thì làm gì — chọn riêng cho từng món quà. */
enum GiftStockRule: string
{
    case TangPhanCon = 'tang_phan_con';
    case KhongTang = 'khong_tang';

    public function label(): string
    {
        return match ($this) {
            self::TangPhanCon => 'Tặng phần còn lại',
            self::KhongTang => 'Không tặng',
        };
    }
}
