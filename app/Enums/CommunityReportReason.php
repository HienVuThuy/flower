<?php

namespace App\Enums;

/** Lý do báo cáo một bài / bình luận Góc cây. */
enum CommunityReportReason: string
{
    case Spam = 'spam';
    case XucPham = 'xuc_pham';
    case LuaDao = 'lua_dao';
    case KhongPhuHop = 'khong_phu_hop';
    case Khac = 'khac';

    public function label(): string
    {
        return match ($this) {
            self::Spam => 'Spam, quảng cáo',
            self::XucPham => 'Xúc phạm, tiêu cực, gây gổ',
            self::LuaDao => 'Lừa đảo hoặc thông tin sai',
            self::KhongPhuHop => 'Nội dung, hình ảnh không phù hợp',
            self::Khac => 'Lý do khác',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
