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
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'quyen' => \App\Http\Middleware\CoQuyen::class,
        ]);

        $middleware->appendToGroup('web', 'throttle:chung');

        $middleware->appendToGroup('web', PreventBackHistory::class);

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->appendToGroup('web', EnsureUserIsNotLocked::class);

        $middleware->appendToGroup('web', \App\Http\Middleware\GhiNhanNgayGhe::class);

        $middleware->redirectGuestsTo('/dang-nhap');

        $middleware->validateCsrfTokens(except: [
            'thanh-toan/momo/ipn',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
