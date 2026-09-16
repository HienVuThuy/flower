<?php

namespace App\Enums;

/** DÁNG của cây hoặc bó hoa. */
enum PlantShape: string
{
    case Upright = 'upright';
    case Bushy = 'bushy';
    case Trailing = 'trailing';
    case Columnar = 'columnar';
    case Rosette = 'rosette';
    case Round = 'round';
    case Cascading = 'cascading';

    public function label(): string
    {
        return match ($this) {
            self::Upright => 'Dáng đứng',
            self::Bushy => 'Dáng bụi xoè',
            self::Trailing => 'Dáng rủ',
            self::Columnar => 'Leo cột, dáng trụ',
            self::Rosette => 'Xoè hoa thị',
            self::Round => 'Dáng tròn',
            self::Cascading => 'Dáng thác đổ',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Upright => 'Vươn thẳng, chiếm ít diện tích sàn — hợp góc phòng',
            self::Bushy => 'Xoè đều các hướng, cần chỗ rộng ngang',
            self::Trailing => 'Buông xuống — hợp kệ cao, tủ, chậu treo',
            self::Columnar => 'Bám cột dựng đứng, cao dần theo thời gian',
            self::Rosette => 'Lá xếp vòng tròn từ tâm, nhìn đẹp từ trên xuống',
            self::Round => 'Khối tròn gọn, hợp bàn làm việc',
            self::Cascading => 'Đổ dài xuống nhiều tầng — dáng bonsai cổ điển',
        };
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
