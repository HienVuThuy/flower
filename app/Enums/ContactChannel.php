<?php

namespace App\Enums;

/**
 * Cách khách muốn cửa hàng liên hệ lại.
 *
 * NGHE NHỎ NHƯNG LÀ CHỖ HỎNG THẬT: nhân viên gọi điện cho một khách
 * doanh nghiệp đang họp cả ngày thì không ai nghe máy, phiếu treo ba
 * hôm, và khách nghĩ cửa hàng bỏ quên mình. Hỏi một câu ở biểu mẫu tiết
 * kiệm được đúng ba hôm đó.
 *
 * Chỉ liệt kê những kênh cửa hàng THẬT SỰ có người trực. Thêm một kênh
 * vào đây mà không ai đọc là hứa một thứ không có.
 */
enum ContactChannel: string
{
    case Phone = 'phone';
    case Zalo = 'zalo';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Gọi điện',
            self::Zalo => 'Nhắn Zalo',
            self::Email => 'Gửi email',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Phone => 'Nhanh nhất, trong giờ hành chính',
            self::Zalo => 'Gửi được ảnh mẫu và bảng giá',
            self::Email => 'Có báo giá bằng văn bản để lưu',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
