<?php

namespace App\Http\Middleware;

use App\Services\Points\VisitStreak;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ghi nhận khách ghé cửa hàng hôm nay — cho chuỗi ngày ghé thăm.
 *
 * Chỉ trang GET mở bằng trình duyệt: gửi biểu mẫu, gọi JSON (ô gợi ý tìm
 * kiếm gọi theo từng phím gõ) và trang quản trị không phải "ghé thăm".
 * Xem VisitStreak.
 */
class GhiNhanNgayGhe
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user !== null
            && $request->isMethod('GET')
            && ! $request->expectsJson()
            && ! $request->is('admin', 'admin/*', 'api/*')) {
            app(VisitStreak::class)->ghiNhan($user);
        }

        return $next($request);
    }
}
