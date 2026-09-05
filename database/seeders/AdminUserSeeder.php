<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Tài khoản quản trị mặc định cho môi trường phát triển.
 * ============================================================
 * CHỈ TẠO KHI CHƯA CÓ. Không đụng vào tài khoản đã tồn tại.
 *
 * LỖI TRƯỚC KHI SỬA: chỗ này dùng `updateOrCreate`, nên mỗi lần chạy
 * `php artisan db:seed` là mật khẩu bị ghi đè trở lại giá trị mặc định
 * trong tệp này.
 *
 * Nghĩa là: người dùng đổi mật khẩu quản trị thành một chuỗi chỉ mình
 * họ biết, vài hôm sau chạy seeder để nạp thêm dữ liệu mẫu, và mật khẩu
 * **âm thầm quay về `Admin@12345`** — một chuỗi nằm sẵn trong mã nguồn,
 * ai đọc repo cũng biết. Không có thông báo nào, không có dòng log nào.
 * Họ vẫn đăng nhập được bằng mật khẩu mới? Không — mật khẩu mới đã mất.
 *
 * `firstOrCreate` chỉ ghi khi CHƯA có bản ghi nào khớp email, nên chạy
 * lại bao nhiêu lần cũng không đụng tới mật khẩu người dùng đã đặt.
 *
 * VẪN GIỮ TÍNH IDEMPOTENT: chạy nhiều lần không tạo tài khoản trùng.
 */
class AdminUserSeeder extends Seeder
{
    /**
     * Mật khẩu mặc định — CHỈ dùng cho lần tạo đầu tiên.
     *
     * Nằm trong mã nguồn nên ai đọc được repo là biết. Chấp nhận được
     * với môi trường phát triển, KHÔNG chấp nhận được trên máy chủ thật:
     * ở đó phải đổi ngay sau lần đăng nhập đầu.
     */
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

        /*
         * Vai trò thì VẪN đặt lại mỗi lần, khác với mật khẩu.
         *
         * Hai thứ này khác nhau về hậu quả: ghi đè mật khẩu làm mất thứ
         * người dùng tự đặt và hạ bảo mật xuống một chuỗi công khai. Đặt
         * lại vai trò thì ngược lại — nó chữa đúng cái tình huống seeder
         * sinh ra để chữa: chạy `db:seed` sau khi lỡ tay hạ quyền và
         * không còn ai vào được khu quản trị.
         */
        if ($admin->role !== UserRole::Admin) {
            $admin->role = UserRole::Admin;
            $admin->save();
        }

        /*
         * Nói rõ chuyện gì vừa xảy ra.
         *
         * Người chạy seeder cần biết mật khẩu có được đặt hay không —
         * im lặng thì họ đoán, và đoán sai theo cả hai hướng đều tốn
         * thời gian: hoặc thử mật khẩu mặc định cho một tài khoản đã đổi,
         * hoặc tưởng mật khẩu đã bị reset trong khi không.
         */
        $this->command?->info($daCo
            ? "Tài khoản {$email} đã có sẵn — GIỮ NGUYÊN mật khẩu hiện tại."
            : "Đã tạo {$email} với mật khẩu mặc định: ".self::MAT_KHAU_MAC_DINH);
    }
}
