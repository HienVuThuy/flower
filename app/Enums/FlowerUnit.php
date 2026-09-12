<?php

namespace App\Enums;

/**
 * Đơn vị mua hoa.
 * ============================================================
 * ĐƠN VỊ NẰM TRÊN TỪNG LÔ, không phải trên loại hoa — vì có hôm mua
 * theo bó ở vựa, có hôm mua theo cân ngoài chợ.
 *
 * HỆ QUẢ PHẢI NHỚ KHI SO GIÁ: giá mỗi bó và giá mỗi cân KHÔNG so được
 * với nhau. Mọi phép so sánh phải gom theo cặp (loại hoa + đơn vị), và
 * bảng so giá hiện chúng thành hai dòng riêng chứ không cộng gộp.
 */
enum FlowerUnit: string
{
    case Canh = 'canh';
    case Bo = 'bo';
    case Chuc = 'chuc';
    case Kg = 'kg';
    case Thung = 'thung';

    public function label(): string
    {
        return match ($this) {
            self::Canh => 'cành',
            self::Bo => 'bó',
            self::Chuc => 'chục',
            self::Kg => 'kg',
            self::Thung => 'thùng',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
