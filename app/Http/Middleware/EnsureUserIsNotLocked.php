<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Đẩy ra ngoài ngay khi tài khoản bị khoá giữa chừng.
 * ============================================================
 * VÌ SAO KHÔNG ĐỦ NẾU CHỈ CHẶN Ở MÀN HÌNH ĐĂNG NHẬP:
 *
 * Phép kiểm trong LoginRequest chỉ chạy đúng một lần, lúc gõ mật khẩu.
 * Sau đó người dùng giữ một PHIÊN đang mở — và phiên đó sống nhiều
 * ngày, có "ghi nhớ đăng nhập" thì lâu hơn nữa.
 *
 * Nghĩa là: quản trị khoá một tài khoản đang gửi đánh giá rác, rồi tài
 * khoản đó VẪN TIẾP TỤC GỬI, vì nó không cần đăng nhập lại. Khoá chỉ có
 * tác dụng từ lần đăng nhập kế tiếp — tức là không có tác dụng gì với
 * đúng người đang cần chặn.
 *
 * Middleware này kiểm ở MỌI request, nên khoá có hiệu lực ngay ở lần
 * bấm tiếp theo của họ.
 *
 * HUỶ CẢ PHIÊN, không chỉ đăng xuất: phiên còn giữ giỏ hàng và các vé
 * xem đơn của khách vãng lai. Đăng xuất suông thì người bị khoá vẫn
 * mang theo những thứ đó sang trạng thái chưa đăng nhập.
 */
class EnsureUserIsNotLocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->isLocked()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('error', $user->lock_reason
                    ? 'Tài khoản của bạn đang bị khoá. Lý do: '.$user->lock_reason
                    : 'Tài khoản của bạn đang bị khoá. Vui lòng liên hệ cửa hàng.');
        }

        return $next($request);
    }
}
