<?php

namespace App\Enums;

/** Một phiên bản báo giá của phiếu chăm hộ. */
enum BoardingQuoteStatus: string
{
    case DangCho = 'dang_cho';
    case DaDongY = 'da_dong_y';
    case YeuCauSua = 'yeu_cau_sua';
    case DaHuy = 'da_huy';
    case ThayThe = 'thay_the';

    public function label(): string
    {
        return match ($this) {
            self::DangCho => 'Đang chờ khách trả lời',
            self::DaDongY => 'Khách đã xác nhận',
            self::YeuCauSua => 'Khách yêu cầu sửa',
            self::DaHuy => 'Khách huỷ phiếu',
            self::ThayThe => 'Đã có báo giá mới thay',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::DangCho, self::YeuCauSua => 'warning',
            self::DaDongY => 'success',
            self::DaHuy, self::ThayThe => 'secondary',
        };
    }
}
