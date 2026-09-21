<?php

namespace App\Enums;

/**
 * DỊP TẶNG của một sản phẩm — nhãn nhiều giá trị, không phải danh mục:
 * cùng một bó hồng hợp cả tình yêu lẫn sinh nhật.
 */
enum GiftOccasion: string
{
    case TinhYeu = 'tinh-yeu';
    case SinhNhat = 'sinh-nhat';
    case ChucMung = 'chuc-mung';
    case SinhVien = 'sinh-vien';
    case ChiaBuon = 'chia-buon';

    public function label(): string
    {
        return match ($this) {
            self::TinhYeu => 'Tình yêu',
            self::SinhNhat => 'Sinh nhật',
            self::ChucMung => 'Chúc mừng',
            self::SinhVien => 'Sinh viên & tốt nghiệp',
            self::ChiaBuon => 'Chia buồn',
        };
    }

    public function heading(): string
    {
        return match ($this) {
            self::TinhYeu => 'Hoa tình yêu',
            self::SinhNhat => 'Hoa sinh nhật',
            self::ChucMung => 'Hoa chúc mừng',
            self::SinhVien => 'Hoa sinh viên & tốt nghiệp',
            self::ChiaBuon => 'Hoa chia buồn',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::TinhYeu => 'Kỷ niệm, hẹn hò, lời muốn nói',
            self::SinhNhat => 'Tặng người thân, bạn bè, đồng nghiệp',
            self::ChucMung => 'Thăng chức, 8/3, 20/10, 20/11',
            self::SinhVien => 'Lễ tốt nghiệp, bảo vệ, tặng thầy cô',
            self::ChiaBuon => 'Viếng, tiễn biệt — tông trang nhã',
        };
    }

    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
