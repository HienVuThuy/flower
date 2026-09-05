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

    /**
     * Xoá danh sách "đơn vừa đặt" của khách VÃNG LAI khỏi phiên.
     * ============================================================
     * LỖ HỔNG ĐÃ ĐO ĐƯỢC:
     *   1. Khách A đặt hàng (không đăng nhập). Mã đơn được ghi vào
     *      session `checkout.placed` — đó là "vé" để họ xem lại đơn.
     *   2. A rời máy mà không đăng xuất (khách vãng lai thì không có nút
     *      đăng xuất để bấm).
     *   3. B ngồi xuống, đăng nhập bằng tài khoản của mình.
     *   4. login() chỉ gọi regenerate() — ĐỔI ID PHIÊN NHƯNG GIỮ NGUYÊN
     *      DỮ LIỆU. Cái vé của A còn nguyên trong phiên của B.
     *   5. B mở /don-hang/{mã đơn của A} và đọc được TÊN, SỐ ĐIỆN THOẠI,
     *      ĐỊA CHỈ NHÀ của A. Bấm huỷ cũng được.
     *
     * Cái vé đó gắn với MỘT NGƯỜI VÔ DANH, không gắn với tài khoản nào.
     * Vừa có người đăng nhập thì nó hết giá trị.
     *
     * KHÔNG tự gán đơn cũ cho tài khoản mới đăng nhập: ở bước 3 hệ thống
     * không thể biết B có phải chính là A hay không, và đoán sai theo
     * hướng đó là trao vĩnh viễn dữ liệu của A cho B. Khách vãng lai muốn
     * xem lại đơn thì dùng trang Tra cứu đơn (mã đơn + số điện thoại).
     */
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

        // role không nằm trong Fillable của User nên phải
        // gán trực tiếp — không thể bị ghi đè từ request.
        $user->role = UserRole::Customer;
        $user->save();

        Log::info('Người dùng mới đăng ký.', ['user_id' => $user->id]);

        /*
         * Lấy session id TRƯỚC khi regenerate.
         * regenerate() đổi session id, mà giỏ hàng của khách vãng lai
         * được lưu theo id cũ — lấy sau thì mất dấu giỏ và khách vừa
         * đăng ký xong sẽ thấy giỏ trống.
         */
        $guestSessionId = $request->session()->getId();

        Auth::login($user);

        $request->session()->regenerate();
        $this->forgetGuestOrders($request);

        $this->carts->mergeSessionCartInto($user->id, $guestSessionId);

        /*
         * GỬI MÃ XÁC THỰC — bọc try/catch, và đây là ngoại lệ có chủ ý.
         *
         * EmailVerifier::send() cố tình KHÔNG nuốt lỗi gửi thư, vì với nó
         * lá thư chính là chức năng. Nhưng ở đây thì khác: tài khoản đã
         * tạo xong và khách đã đăng nhập. Để lỗi SMTP ném ra trang trắng
         * là làm hỏng một việc đã thành công — họ sẽ đăng ký lại và gặp
         * lỗi "email đã tồn tại", không hiểu chuyện gì.
         *
         * Hỏng thì đưa họ tới trang nhập mã kèm lời nhắn, ở đó có nút
         * "Gửi lại mã" để thử lại.
         */
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
        /*
         * ?redirect= — quay lại đúng chỗ khách đang đứng.
         *
         * Middleware `auth` tự đặt url.intended khi nó CHẶN một request.
         * Nhưng có những chỗ khách chủ động bấm "Đăng nhập để làm X" khi
         * chưa bị chặn gì cả — ví dụ nút Lưu voucher. Lúc đó không có
         * intended nào được đặt, và đăng nhập xong họ bị ném về trang chủ
         * rồi phải tự đi tìm lại chỗ cũ.
         *
         * CHỈ NHẬN ĐƯỜNG DẪN NỘI BỘ. Không kiểm thì đây là lỗ hổng
         * "chuyển hướng mở": kẻ xấu gửi link /login?redirect=https://
         * trang-gia.example, khách đăng nhập thật xong bị đẩy sang trang
         * giả trông y hệt và nhập lại mật khẩu ở đó.
         */
        $redirect = (string) $request->query('redirect', '');

        if ($redirect !== '' && $this->isInternalUrl($redirect, $request)) {
            $request->session()->put('url.intended', $redirect);
        }

        return view('auth.login');
    }

    /**
     * Đường dẫn có thuộc chính website này không.
     *
     * Chấp nhận hai dạng: đường dẫn tương đối bắt đầu bằng một dấu "/",
     * hoặc URL tuyệt đối cùng host với ứng dụng.
     */
    private function isInternalUrl(string $url, Request $request): bool
    {
        // "//trang-khac.example" là URL tuyệt đối theo giao thức hiện tại,
        // trông giống đường dẫn nội bộ nhưng dẫn ra ngoài. Loại trước.
        if (str_starts_with($url, '//') || str_starts_with($url, '/\\')) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return true;
        }

        /*
         * URL tuyệt đối: phải cùng host.
         *
         * So với CẢ host của request lẫn APP_URL. Chỉ so APP_URL thì trên
         * máy phát triển sẽ hỏng — APP_URL thường là "http://localhost"
         * trong khi người ta mở bằng "127.0.0.1:8000", hai chuỗi khác
         * nhau nên mọi đường dẫn hợp lệ đều bị từ chối. Đo được đúng vậy
         * khi thử lần đầu.
         */
        $host = parse_url($url, PHP_URL_HOST);

        return $host !== null && in_array($host, [
            $request->getHost(),
            parse_url((string) config('app.url'), PHP_URL_HOST),
        ], true);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        // Xem giải thích ở register(): phải lấy trước khi regenerate.
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
