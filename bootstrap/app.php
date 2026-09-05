<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsNotLocked;
use App\Http\Middleware\PreventBackHistory;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        // Trang đã đăng nhập không được lưu trong bfcache của trình
        // duyệt — nếu không, nhấn Back sau khi đăng xuất vẫn thấy
        // nội dung riêng tư. Middleware tự bỏ qua khách vãng lai.
        $middleware->appendToGroup('web', PreventBackHistory::class);

        /*
         * Tài khoản bị khoá phải bị đẩy ra NGAY, không đợi tới lần đăng
         * nhập sau — xem EnsureUserIsNotLocked.
         *
         * Đặt vào nhóm 'web' chứ không gắn riêng từng route: bỏ sót một
         * route là để lại đúng một cánh cửa, và cánh cửa đó sẽ được tìm
         * ra. Middleware tự bỏ qua khách chưa đăng nhập.
         */
        $middleware->appendToGroup('web', EnsureUserIsNotLocked::class);

        // Người chưa đăng nhập bị chặn bởi middleware "auth"
        // sẽ được đưa về trang login thay vì nhận lỗi 401.
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
