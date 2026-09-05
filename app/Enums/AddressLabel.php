<?php

namespace App\Enums;

/**
 * Loại địa chỉ — chỉ để khách nhận ra nhanh trong danh sách của mình.
 *
 * Không mang ý nghĩa nghiệp vụ nào khác: không đổi phí giao, không đổi
 * thời gian giao. Nếu sau này cần phân biệt giờ giao theo loại địa chỉ
 * thì mới thêm logic, hiện tại chưa cần (Guide §27).
 */
enum AddressLabel: string
{
    case Home = 'home';
    case Office = 'office';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Nhà riêng',
            self::Office => 'Văn phòng',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
