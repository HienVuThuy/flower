<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Header bảo mật cho mọi trang: chống nhúng iframe, chống đoán kiểu tệp, giới hạn Referer. */
class SecurityHeaders
{
    private const HEADERS = [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $ten => $giaTri) {
            if (! $response->headers->has($ten)) {
                $response->headers->set($ten, $giaTri);
            }
        }

        return $response;
    }
}
