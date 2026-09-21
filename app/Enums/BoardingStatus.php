<?php

namespace App\Enums;

/** Trạng thái phiếu gửi cây chăm hộ. */
enum BoardingStatus: string
{
    case ChoDuyet = 'cho_duyet';
    case ChoKhachDuyet = 'cho_khach_duyet';
    case DaXacNhan = 'da_xac_nhan';
    case DangCham = 'dang_cham';
    case ChoTra = 'cho_tra';
    case DaTra = 'da_tra';
    case DaHuy = 'da_huy';
    case TuChoi = 'tu_choi';

    public function label(): string
    {
        return match ($this) {
            self::ChoDuyet => 'Chờ cửa hàng xem và báo giá',
            self::ChoKhachDuyet => 'Chờ khách xác nhận báo giá',
            self::DaXacNhan => 'Đã xác nhận, chờ nhận cây',
            self::DangCham => 'Cửa hàng đang chăm',
            self::ChoTra => 'Sắp trả cây',
            self::DaTra => 'Đã trả cây',
            self::DaHuy => 'Đã huỷ',
            self::TuChoi => 'Cửa hàng từ chối',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::ChoDuyet, self::ChoKhachDuyet, self::ChoTra => 'warning',
            self::DaXacNhan, self::DangCham => 'info',
            self::DaTra => 'success',
            self::DaHuy, self::TuChoi => 'secondary',
        };
    }

    /** Khách còn tự huỷ được — cây chưa về cửa hàng. */
    public function khachHuyDuoc(): bool
    {
        return in_array($this, [self::ChoDuyet, self::ChoKhachDuyet, self::DaXacNhan], true);
    }

    public function daKetThuc(): bool
    {
        return in_array($this, [self::DaTra, self::DaHuy, self::TuChoi], true);
    }

    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $c) {
            $out[$c->value] = $c->label();
        }

        return $out;
    }
}
