<?php

namespace App\Enums;

enum InquiryStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Quoted = 'quoted';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Mới',
            self::Contacted => 'Đã liên hệ',
            self::Quoted => 'Đã báo giá',
            self::Closed => 'Đã đóng',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::New => 'text-bg-warning',
            self::Contacted => 'text-bg-info',
            self::Quoted => 'text-bg-primary',
            self::Closed => 'text-bg-secondary',
        };
    }
}
