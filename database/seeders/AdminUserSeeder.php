<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Tài khoản quản trị mặc định cho môi trường phát triển. */
class AdminUserSeeder extends Seeder
{
    private const MAT_KHAU_MAC_DINH = 'Admin@12345';

    public function run(): void
    {
        $email = 'admin@flowerplant.test';

        $daCo = User::where('email', $email)->exists();

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Quản trị viên',
                'password' => self::MAT_KHAU_MAC_DINH,
            ],
        );

        if ($admin->role !== UserRole::Admin) {
            $admin->role = UserRole::Admin;
            $admin->save();
        }

        $this->command?->info($daCo
            ? "Tài khoản {$email} đã có sẵn — GIỮ NGUYÊN mật khẩu hiện tại."
            : "Đã tạo {$email} với mật khẩu mặc định: ".self::MAT_KHAU_MAC_DINH);
    }
}
