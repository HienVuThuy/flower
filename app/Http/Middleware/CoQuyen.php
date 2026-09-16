<?php

namespace App\Http\Middleware;

use App\Enums\Quyen;
use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Chặn theo KHU VỰC quản trị, không theo vai trò. */
class CoQuyen
{
    public function handle(Request $request, Closure $next, string ...$ma): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        foreach ($ma as $m) {
            $quyen = Quyen::tryFrom($m);

            if ($quyen === null) {
                throw new \InvalidArgumentException(
                    'Không có quyền nào tên "' . $m . '". Xem App\Enums\Quyen.',
                );
            }

            if ($user->duoc($quyen)) {
                return $next($request);
            }
        }

        abort(403, 'Tài khoản của bạn không có quyền vào khu vực này.');
    }
}
