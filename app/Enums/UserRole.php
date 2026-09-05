<?php

namespace App\Enums;

/**
 * Vai trò người dùng trong hệ thống.
 *
 * Chỉ giữ 2 vai trò cho phạm vi hiện tại (admin/customer).
 * Nếu sau này cần thêm vai trò (staff, vendor...), chỉ cần
 * thêm case mới ở đây — middleware `role:` và các nơi kiểm
 * tra quyền đều dùng enum này nên không phải sửa nhiều nơi.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Quản trị viên',
            self::Customer => 'Khách hàng',
        };
    }
}
