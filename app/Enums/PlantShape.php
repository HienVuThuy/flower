<?php

namespace App\Enums;

/**
 * DÁNG của cây hoặc bó hoa.
 * ============================================================
 * Đây là tiêu chí về CHỖ ĐẶT VÀ THẨM MỸ, không phải về sinh học. Khách
 * hỏi kiểu: "có cây nào cao cao đặt góc phòng không", "cần cây rủ xuống
 * cho kệ sách".
 *
 * KHÁC `GrowthForm`: một cây thân leo có thể để rủ (`Trailing`) hoặc cho
 * leo cột (`Columnar`) — cùng dạng sống, hai dáng khác nhau, và khách
 * chọn theo dáng vì đó là thứ quyết định nó hợp với chỗ nào trong nhà.
 *
 * Giữ danh sách NGẮN. Dáng là thứ nhìn ảnh là biết; một bộ lọc mười lăm
 * lựa chọn ở đây chỉ làm khách phải đọc nhiều hơn là ngó ảnh.
 */
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

    /** Hợp với chỗ nào — xem chú thích đầu tệp. */
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
