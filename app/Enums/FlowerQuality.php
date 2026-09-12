<?php

namespace App\Enums;

/**
 * Chất lượng lô hoa khi nhận.
 * ============================================================
 * VÌ SAO CẦN, dù nó là đánh giá cảm tính.
 *
 * Câu hỏi thật của người đi lấy hàng không phải "ở đâu rẻ nhất" mà là "ở
 * đâu ĐÁNG TIỀN NHẤT". Một vựa rẻ hơn 10% nhưng hoa hay dập thì đắt
 * hơn, chỉ là cái đắt đó không nằm trên hoá đơn.
 *
 * Ba mức thôi. Thang 10 điểm nghe khoa học hơn nhưng không ai chấm nổi
 * ổn định, và một thang không ổn định thì so sánh trên nó là vô nghĩa.
 */
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
