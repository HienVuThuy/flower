<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Chặn truy cập theo vai trò, dùng chung cho mọi vai trò hiện có và sau này (không cần viết middleware riêng… */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            throw new AuthenticationException;
        }

        $allowed = collect($roles)
            ->filter(fn (string $role) => UserRole::tryFrom($role) !== null)
            ->contains(fn (string $role) => $user->role === UserRole::from($role));

        if (! $allowed) {
            abort(403, 'Bạn không có quyền truy cập trang này.');
        }

        return $next($request);
    }
}
