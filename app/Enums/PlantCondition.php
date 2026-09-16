<?php

namespace App\Enums;

/** Tình trạng cây tại một thời điểm ghi nhật ký. */
enum PlantCondition: string
{
    case Thriving = 'thriving';
    case Healthy = 'healthy';
    case Recovering = 'recovering';
    case Struggling = 'struggling';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Thriving => 'Rất tốt',
            self::Healthy => 'Bình thường',
            self::Recovering => 'Đang hồi',
            self::Struggling => 'Có vấn đề',
            self::Critical => 'Nguy kịch',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Thriving => 'Ra lá mới, màu đậm, thân cứng cáp',
            self::Healthy => 'Không ra lá mới nhưng cũng không xuống',
            self::Recovering => 'Từng yếu, nay đã có dấu hiệu tốt lên',
            self::Struggling => 'Vàng lá, rụng lá, chậm lớn thấy rõ',
            self::Critical => 'Thân mềm, thối gốc, có thể mất cây',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Thriving => 'success',
            self::Healthy => 'info',
            self::Recovering => 'primary',
            self::Struggling => 'warning',
            self::Critical => 'danger',
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
