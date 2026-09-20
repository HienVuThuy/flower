<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Header bảo mật cho mọi trang: chống nhúng iframe, chống đoán kiểu tệp, giới hạn Referer, giới hạn nguồn tài nguyên. */
class SecurityHeaders
{
    private const HEADERS = [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'X-Permitted-Cross-Domain-Policies' => 'none',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
    ];

    /**
     * Content-Security-Policy: chặn kịch bản tải từ tên miền lạ — lớp chắn thứ hai nếu
     * có chỗ nào đó lọt XSS. Vẫn phải để 'unsafe-inline' vì giao diện còn vài đoạn
     * script và thuộc tính onchange nội tuyến; gỡ hết chúng thì mới siết chặt hơn được.
     */
    private const CSP = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: blob:",
        "media-src 'self'",
        "font-src 'self'",
        "connect-src 'self'",
        "frame-src https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com",
        "frame-ancestors 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "object-src 'none'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $ten => $giaTri) {
            if (! $response->headers->has($ten)) {
                $response->headers->set($ten, $giaTri);
            }
        }

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', implode('; ', self::CSP));
        }

        /* Chỉ khi đã chạy HTTPS thật: bắt trình duyệt nhớ luôn dùng HTTPS cho tên miền này. */
        if ($request->secure() && ! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
