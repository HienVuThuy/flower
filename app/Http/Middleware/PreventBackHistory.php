<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chặn trình duyệt lưu lại nội dung của các trang cần đăng nhập.
 *
 * Vì sao cần: mặc định Laravel trả `Cache-Control: no-cache, private`.
 * `no-cache` chỉ yêu cầu revalidate khi request lại — nó KHÔNG ngăn
 * back-forward cache (bfcache). Nhấn Back sau khi đăng xuất, trình
 * duyệt khôi phục nguyên trang đã render từ bfcache mà không hỏi
 * server, nên vẫn thấy nội dung riêng tư của phiên trước.
 *
 * `no-store` mới thực sự cấm lưu, và làm trang không đủ điều kiện
 * vào bfcache — đây là cách xử lý đúng.
 *
 * Chỉ áp cho request đã đăng nhập, nên các trang công khai
 * (trang chủ, danh mục, sản phẩm) vẫn được cache bình thường.
 */
class PreventBackHistory
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user()) {
            $response->headers->set(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, max-age=0'
            );
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
