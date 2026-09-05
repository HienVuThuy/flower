<?php

namespace App\Enums;

/**
 * Tình trạng cây tại một thời điểm ghi nhật ký.
 * ============================================================
 * NĂM MỨC, KHÔNG PHẢI BA. Ba mức (tốt / bình thường / yếu) nghe gọn
 * nhưng dồn hai chuyện rất khác nhau vào cùng một ô "yếu": cây đang
 * xuống dần và cây sắp chết. Người ghi nhật ký cần phân biệt hai cái đó
 * — chúng dẫn tới hai cách xử lý khác hẳn.
 *
 * Mức "Đang hồi" cũng cần riêng: nó là "yếu nhưng đang tốt lên", và đó
 * là thông tin quan trọng nhất khi nhìn lại xem cách chăm vừa đổi có ăn
 * thua không.
 *
 * TẤT CẢ ĐỀU KHÔNG BẮT BUỘC. Một trang nhật ký chỉ ghi "hôm nay thay
 * chậu" thì không cần đánh giá tình trạng, và ép chọn là ép người ta bịa
 * ra một nhận định họ chưa có.
 */
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

    /** Dấu hiệu nhận biết — để người ghi tự đối chiếu thay vì đoán. */
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

    /** Màu huy hiệu — dùng lại thang màu trạng thái sẵn có của dự án. */
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

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
