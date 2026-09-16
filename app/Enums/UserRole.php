<?php

namespace App\Enums;

/** Vai trò người dùng, và khu vực quản trị mà mỗi vai trò vào được. */
enum UserRole: string
{
    case Admin = 'admin';
    case Staff = 'staff';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Chủ cửa hàng',
            self::Staff => 'Nhân viên',
            self::Customer => 'Khách hàng',
        };
    }

    public function moTa(): string
    {
        return match ($this) {
            self::Admin => 'Toàn quyền, gồm giá vốn, lãi gộp, phân quyền và cấu hình.',
            self::Staff => 'Xử lý đơn, kho, đánh giá và xem báo cáo bán hàng.',
            self::Customer => 'Không vào được trang quản trị.',
        };
    }

    public function quyen(): array
    {
        return match ($this) {
            self::Admin => Quyen::cases(),

            self::Staff => [
                Quyen::DonHang,
                Quyen::Kho,
                Quyen::DanhGia,
                Quyen::BaoCao,
            ],

            self::Customer => [],
        };
    }

    public function laNhanSu(): bool
    {
        return $this->quyen() !== [];
    }

    public static function nhanSu(): array
    {
        return array_values(array_filter(self::cases(), fn (self $v) => $v->laNhanSu()));
    }
}
