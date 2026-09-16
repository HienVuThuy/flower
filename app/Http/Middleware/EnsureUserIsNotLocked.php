<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Đẩy ra ngoài ngay khi tài khoản bị khoá giữa chừng. */
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
