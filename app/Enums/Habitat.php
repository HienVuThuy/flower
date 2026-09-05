<?php

namespace App\Enums;

/**
 * MÔI TRƯỜNG SỐNG TỰ NHIÊN của cây.
 * ============================================================
 * KHÁC HẲN `Placement`, và hai thứ này rất hay bị lẫn:
 *
 *   - `Placement` = khách định ĐẶT cây ở đâu trong nhà (phòng ngủ, ban
 *     công, bàn làm việc). Đó là câu hỏi về CĂN NHÀ.
 *   - `Habitat` = ngoài thiên nhiên cây này vốn mọc ở đâu (rừng ẩm, sa
 *     mạc, dưới nước, trên đá). Đó là câu hỏi về CÂY.
 *
 * Vì sao cần cả hai: môi trường sống là thứ giải thích cách chăm. Một
 * cây sa mạc và một cây rừng ẩm có thể cùng đặt được ở phòng khách,
 * nhưng tưới giống nhau thì một trong hai sẽ chết. Khách biết mình đang
 * mua "cây sa mạc" thì hiểu ngay vì sao hướng dẫn ghi "tưới 10 ngày một
 * lần".
 *
 * Đây cũng là cách khách tìm theo sở thích: có người thích cây thuỷ
 * sinh, có người mê xương rồng — họ tìm theo NHÓM SINH THÁI chứ không
 * theo danh mục bán hàng.
 */
enum Habitat: string
{
    case Terrestrial = 'terrestrial';
    case Underground = 'underground';
    case Aquatic = 'aquatic';
    case Semiaquatic = 'semiaquatic';
    case Desert = 'desert';
    case Lithophyte = 'lithophyte';
    case Epiphyte = 'epiphyte';
    case Rainforest = 'rainforest';
    case Temperate = 'temperate';

    public function label(): string
    {
        return match ($this) {
            self::Terrestrial => 'Trên mặt đất',
            self::Underground => 'Có phần sống dưới đất',
            self::Aquatic => 'Dưới nước',
            self::Semiaquatic => 'Nửa nước nửa cạn',
            self::Desert => 'Sa mạc, khô hạn',
            self::Lithophyte => 'Bám đá',
            self::Epiphyte => 'Bám thân cây khác',
            self::Rainforest => 'Rừng ẩm nhiệt đới',
            self::Temperate => 'Vùng ôn đới',
        };
    }

    /**
     * Điều đó có nghĩa gì với người mua.
     *
     * Nhãn khoa học không giúp ai chăm cây. Câu này mới là thứ khách cần:
     * nó dịch một đặc điểm sinh thái thành một việc phải làm.
     */
    public function hint(): string
    {
        return match ($this) {
            self::Terrestrial => 'Trồng chậu đất bình thường, tưới khi mặt đất se khô',
            self::Underground => 'Củ hoặc thân ngầm dưới đất — sợ úng hơn sợ hạn',
            self::Aquatic => 'Sống trong nước, không trồng đất',
            self::Semiaquatic => 'Chịu được gốc ngập, thích đất luôn ẩm',
            self::Desert => 'Rất chịu hạn, tưới thưa; úng nước là chết',
            self::Lithophyte => 'Bám đá trong tự nhiên, cần thoát nước thật nhanh',
            self::Epiphyte => 'Bám cây khác, rễ cần thoáng khí — không nén chặt đất',
            self::Rainforest => 'Ưa ẩm và sáng gián tiếp, sợ nắng gắt',
            self::Temperate => 'Quen khí hậu mát, cần chỗ thoáng khi trời nóng',
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
