<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/** Mở trước mọi trang quản trị một lần, để lần bấm đầu tiên không chậm. */
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
