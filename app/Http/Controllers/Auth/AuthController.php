<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\CheckoutController;
use App\Services\Auth\EmailVerifier;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\Cart\CartService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly EmailVerifier $verifier,
    ) {
    }

    private function forgetGuestOrders(Request $request): void
    {
        $request->session()->forget(CheckoutController::PLACED_KEY);
    }

    public function showRegistrationForm(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->role = UserRole::Customer;
        $user->save();

        Log::info('Người dùng mới đăng ký.', ['user_id' => $user->id]);

        $guestSessionId = $request->session()->getId();

        Auth::login($user);

        $request->session()->regenerate();
        $this->forgetGuestOrders($request);

        $this->carts->mergeSessionCartInto($user->id, $guestSessionId);

        try {
            $this->verifier->send($user);
        } catch (\Throwable $e) {
            Log::error('Không gửi được mã xác thực sau khi đăng ký.', [
                'user_id' => $user->id,
                'loi' => $e->getMessage(),
            ]);

            return redirect()
                ->route('verification.notice')
                ->with('error', 'Tài khoản đã tạo xong nhưng chưa gửi được mã. Bấm "Gửi lại mã" bên dưới.');
        }

        return redirect()
            ->route('verification.notice')
            ->with('success', 'Đăng ký thành công! Nhập mã vừa gửi tới email để hoàn tất.');
    }

    public function showLoginForm(Request $request): View
    {
        $redirect = (string) $request->query('redirect', '');

        if ($redirect !== '' && $this->isInternalUrl($redirect, $request)) {
            $request->session()->put('url.intended', $redirect);
        }

        return view('auth.login');
    }

    private function isInternalUrl(string $url, Request $request): bool
    {
        if (str_starts_with($url, '//') || str_starts_with($url, '/\\')) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return $host !== null && in_array($host, [
            $request->getHost(),
            parse_url((string) config('app.url'), PHP_URL_HOST),
        ], true);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $guestSessionId = $request->session()->getId();

        $request->authenticate();

        $request->session()->regenerate();
        $this->forgetGuestOrders($request);

        $this->carts->mergeSessionCartInto(Auth::id(), $guestSessionId);

        if (Auth::user()->isAdmin()) {
            return redirect()
                ->intended(route('admin.dashboard'));
        }

        return redirect()
            ->intended(route('welcome'))
            ->with('success', 'Đăng nhập thành công.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('welcome')
            ->with('success', 'Bạn đã đăng xuất.');
    }
}
