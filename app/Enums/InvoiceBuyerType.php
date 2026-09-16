<?php

namespace App\Enums;

/** Người mua trên hoá đơn là cá nhân hay tổ chức. */
enum InvoiceBuyerType: string
{
    case Personal = 'personal';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Personal => 'Cá nhân',
            self::Company => 'Công ty / tổ chức',
        };
    }

    public function requiresTaxCode(): bool
    {
        return $this === self::Company;
    }

    public function hint(): string
    {
        return match ($this) {
            self::Personal => 'Hoá đơn đứng tên bạn, không có mã số thuế.',
            self::Company => 'Cần mã số thuế và địa chỉ đăng ký để công ty khấu trừ được.',
        };
    }
}
