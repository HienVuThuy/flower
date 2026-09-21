<?php

namespace App\Enums;

/** Khách gửi cây chăm hộ trong bao lâu. */
enum BoardingMode: string
{
    case Thang = 'thang';
    case Nam = 'nam';
    case DenNgay = 'den_ngay';
    case TheoDip = 'theo_dip';
    case KhongHen = 'khong_hen';

    public function label(): string
    {
        return match ($this) {
            self::Thang => 'Theo tháng',
            self::Nam => 'Theo năm',
            self::DenNgay => 'Đến một ngày cụ thể',
            self::TheoDip => 'Nhận lại đúng dịp lễ',
            self::KhongHen => 'Chưa hẹn ngày — báo là trả',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Thang => 'Gửi trọn số tháng chọn.',
            self::Nam => 'Gửi trọn năm, áp giá năm.',
            self::DenNgay => 'Chọn ngày muốn nhận cây lại.',
            self::TheoDip => 'Cửa hàng mang cây về đúng trước dịp (ví dụ Tết), qua dịp thì nhận lại chăm tiếp nếu bạn muốn.',
            self::KhongHen => 'Cần lúc nào báo lúc đó; tính tiền theo số tháng thực gửi.',
        };
    }
}
