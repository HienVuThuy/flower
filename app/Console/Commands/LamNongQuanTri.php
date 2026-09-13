<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Mở trước mọi trang quản trị một lần, để lần bấm đầu tiên không chậm.
 * ============================================================
 * VÌ SAO CẦN — ĐO ĐƯỢC, không phỏng đoán:
 *
 * Sau mỗi lần sửa layout, thanh bên hay bộ icon, Blade biên dịch lại
 * view ở LẦN MỞ ĐẦU TIÊN của từng trang. Đo trên chính dự án này: trang
 * Tổng quan lần đầu 8.524ms, lần sau 73ms; Đơn hàng 1.183ms → 31ms. Người
 * dùng bấm qua các mục ngay sau khi cập nhật sẽ thấy "chậm hơn trước",
 * dù mã mới không chậm hơn mã cũ.
 *
 * `php artisan view:cache` không giải được việc này trên Windows (xem
 * docs/HIEU-NANG.md). Lệnh này đi qua đúng đường một request thật — nên
 * mọi view và component mà trang dùng tới đều được biên dịch sẵn.
 *
 * CHỈ ĐỌC: chỉ gọi đường dẫn GET, không tham số, trong khu quản trị; bỏ
 * qua đường dẫn tải tệp xuống.
 */
class LamNongQuanTri extends Command
{
    protected $signature = 'quan-tri:lam-nong';

    protected $description = 'Mở trước mọi trang quản trị một lần để Blade biên dịch sẵn (chạy sau mỗi lần cập nhật giao diện).';

    public function handle(Kernel $http): int
    {
        $admin = User::where('role', UserRole::Admin->value)->orderBy('id')->first();

        if (! $admin) {
            $this->warn('Chưa có tài khoản quản trị — không mở được trang nào.');

            return self::FAILURE;
        }

        $duongDan = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods(), true)
                && str_starts_with($r->uri(), 'admin')
                && ! str_contains($r->uri(), '{')
                && ! str_contains($r->uri(), 'tai-ve'))
            ->map(fn ($r) => '/' . $r->uri())
            ->unique()
            ->values();

        $loi = 0;
        $tong = 0.0;

        foreach ($duongDan as $url) {
            $req = Request::create($url, 'GET');
            $req->setLaravelSession(app('session')->driver());
            Auth::setUser($admin);

            $bat = microtime(true);
            $res = $http->handle($req);
            $ms = (microtime(true) - $bat) * 1000;
            $tong += $ms;

            $ok = $res->getStatusCode() < 400;
            $loi += $ok ? 0 : 1;

            $this->line(sprintf('%s %4d %7.0fms  %s', $ok ? '✓' : '✗', $res->getStatusCode(), $ms, $url));
        }

        $this->info(sprintf('Đã mở %d trang trong %.1f giây.', $duongDan->count(), $tong / 1000));

        return $loi === 0 ? self::SUCCESS : self::FAILURE;
    }
}
