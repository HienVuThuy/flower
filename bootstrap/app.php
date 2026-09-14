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
            'quyen' => \App\Http\Middleware\CoQuyen::class,
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

        // SAU phép kiểm khoá: tài khoản bị khoá không được tính là đã ghé.
        $middleware->appendToGroup('web', \App\Http\Middleware\GhiNhanNgayGhe::class);

        // Người chưa đăng nhập bị chặn bởi middleware "auth"
        // sẽ được đưa về trang login thay vì nhận lỗi 401.
        $middleware->redirectGuestsTo('/login');

        /*
         * IPN CỦA CỔNG THANH TOÁN KHÔNG CÓ TOKEN CSRF.
         *
         * MoMo gọi thẳng từ máy chủ của họ vào đây, không qua trình
         * duyệt nào nên không có phiên và không có token. Bảo vệ ở đây
         * KHÔNG mất đi mà đổi sang một thứ mạnh hơn: chữ ký HMAC ký bằng
         * khoá bí mật — xem MomoGateway::verifySignature().
         *
         * Chỉ miễn đúng địa chỉ IPN. Trang kết quả cho khách là GET nên
         * vốn không cần token.
         */
        $middleware->validateCsrfTokens(except: [
            'thanh-toan/momo/ipn',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
