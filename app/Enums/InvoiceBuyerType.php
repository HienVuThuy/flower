<?php

namespace App\Enums;

/**
 * Người mua trên hoá đơn là cá nhân hay tổ chức.
 * ============================================================
 * KHÔNG PHẢI MỘT NHÃN TRANG TRÍ — nó quyết định trường nào bắt buộc.
 *
 * Cá nhân không có mã số thuế; tổ chức thì hoá đơn KHÔNG hợp lệ nếu
 * thiếu mã số thuế và địa chỉ. Nếu để một biểu mẫu chung "điền gì cũng
 * được", cửa hàng sẽ thu về một đống dữ liệu hoá đơn không dùng nổi và
 * chỉ phát hiện lúc đi phát hành.
 */
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

    /** Loại này có bắt buộc mã số thuế và địa chỉ không. */
    public function requiresTaxCode(): bool
    {
        return $this === self::Company;
    }

    /**
     * Câu giải thích ngay dưới ô chọn.
     *
     * Khách chọn nhầm "Cá nhân" rồi tháng sau kế toán công ty không
     * khấu trừ được — lúc đó hoá đơn đã phát hành và sửa rất phiền. Một
     * câu ở đây rẻ hơn nhiều.
     */
    public function hint(): string
    {
        return match ($this) {
            self::Personal => 'Hoá đơn đứng tên bạn, không có mã số thuế.',
            self::Company => 'Cần mã số thuế và địa chỉ đăng ký để công ty khấu trừ được.',
        };
    }
}
