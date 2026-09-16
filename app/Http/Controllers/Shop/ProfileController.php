<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\ChangePasswordRequest;
use App\Http\Requests\Shop\ProfileRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\Auth\AccountDeleter;
use App\Services\Auth\AccountDeletionException;
use App\Services\Auth\AccountSecurity;
use App\Services\Auth\ActiveSessions;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/** Hồ sơ tài khoản của khách (Guide §11 — "User → Hồ sơ"). */
class ProfileController extends Controller
{
    public function __construct(
        private readonly AccountSecurity $security,
        private readonly ActiveSessions $sessions,
        private readonly AccountDeleter $deleter,
    ) {
    }

    private const MUC = [
        'thong-tin' => 'Thông tin',
        'diem-thuong' => 'Điểm thưởng',
        'hang-thanh-vien' => 'Hạng thành viên',
        'tra-gop' => 'Trả góp & tín dụng',
        'bao-mat' => 'Bảo mật',
        'tuy-chon' => 'Tuỳ chọn',
    ];

    public function edit(Request $request): View
    {
        $user = Auth::user();

        $muc = (string) $request->query('muc');

        if (! array_key_exists($muc, self::MUC)) {
            $muc = array_key_first(self::MUC);
        }

        return view('shop.profile.edit', [
            'muc' => $muc,
            'cacMuc' => self::MUC,
            'user' => $user,

            'sessions' => $this->sessions->forUser($user, $request->session()->getId()),
            'sessionsSupported' => $this->sessions->supported(),

            'orderCount' => Order::where('user_id', $user->id)->count(),
            'wishlistCount' => $user->wishlists()->count(),
            'reviewCount' => $user->reviews()->count(),
            'diemSoDu' => app(\App\Services\Points\PointLedger::class)->soDu($user),

            'diem' => $muc === 'diem-thuong' ? [
                'so_du' => app(\App\Services\Points\PointLedger::class)->soDu($user),
                'lich_su' => app(\App\Services\Points\PointLedger::class)->lichSu($user),
                'goi' => \App\Services\Points\PointLedger::GOI,
                'chuoi' => app(\App\Services\Points\VisitStreak::class)->hienTai($user),
            ] : null,
        ]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $data = $request->validated();

        $emailChanged = $data['email'] !== $user->email;

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->save();

        if ($emailChanged) {
            Log::info('Người dùng đổi địa chỉ email.', ['user_id' => $user->id]);
        }

        return back()->with('success', $emailChanged
            ? 'Đã cập nhật thông tin. Lần đăng nhập sau hãy dùng email mới.'
            : 'Đã cập nhật thông tin tài khoản.');
    }

    public function updatePassword(ChangePasswordRequest $request): RedirectResponse
    {
        $user = Auth::user();

        $currentSessionId = $request->session()->getId();

        $killed = $this->security->changePassword(
            $user,
            $request->validated('password'),
            $currentSessionId,
            $request->ip(),
        );

        $request->session()->regenerate(true);

        Log::info('Người dùng đổi mật khẩu.', [
            'user_id' => $user->id,
            'sessions_killed' => $killed,
        ]);

        return back()->with('success', $killed > 0
            ? sprintf('Đã đổi mật khẩu. %d phiên đăng nhập trên thiết bị khác đã bị đăng xuất.', $killed)
            : 'Đã đổi mật khẩu.');
    }

    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            Log::warning('Không gửi được liên kết đặt lại mật khẩu từ trang hồ sơ.', [
                'user_id' => $user->id,
                'status' => $status,
            ]);

            return back()->with(
                'error',
                'Chưa gửi được thư lúc này. Vui lòng thử lại sau ít phút.',
            );
        }

        return back()->with(
            'success',
            'Đã gửi liên kết đặt lại mật khẩu tới '.$user->email
            .'. Liên kết có hạn 60 phút.',
        );
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $user->notify_order_updates = $request->boolean('notify_order_updates');

        $user->notify_care_reminders = $request->boolean('notify_care_reminders');

        $user->save();

        return back()->with('success', 'Đã lưu tuỳ chọn nhận thư.');
    }

    public function revokeSession(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $sessionId = (string) $request->input('session_id');
        $currentSessionId = $request->session()->getId();

        if ($sessionId === $currentSessionId) {
            return back()->with('error', 'Đây là thiết bị bạn đang dùng. Hãy dùng nút Đăng xuất.');
        }

        $revoked = $this->sessions->revoke($user, $sessionId);

        if (! $revoked) {
            return back()->with('error', 'Không tìm thấy phiên đăng nhập đó. Có thể nó đã hết hạn.');
        }

        Log::info('Người dùng đá một phiên đăng nhập.', ['user_id' => $user->id]);

        return back()->with('success', 'Đã đăng xuất thiết bị đó.');
    }

    public function requestDeletion(Request $request): RedirectResponse
    {
        try {
            $this->deleter->sendConfirmation(Auth::user());
        } catch (AccountDeletionException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Không gửi được thư xác nhận xoá tài khoản.', [
                'user_id' => Auth::id(),
                'exception' => $e->getMessage(),
            ]);

            return back()->with('error', 'Chưa gửi được thư lúc này. Vui lòng thử lại sau ít phút.');
        }

        return back()->with(
            'success',
            'Đã gửi thư xác nhận tới '.Auth::user()->email
            .'. Mở thư và bấm liên kết trong đó để tiếp tục — liên kết có hạn '
            .AccountDeleter::TTL_MINUTES.' phút.',
        );
    }

    public function confirmDeletion(Request $request, User $user): View|RedirectResponse
    {
        if (! $user->is(Auth::user())) {
            return redirect()
                ->route('shop.profile.edit')
                ->with('error', 'Liên kết này thuộc về một tài khoản khác.');
        }

        return view('shop.profile.delete', [
            'user' => $user,
            'summary' => $this->deleter->summary($user),
            'cauXacNhan' => AccountDeleter::CAU_XAC_NHAN,
            'signedQuery' => $request->getQueryString(),
        ]);
    }

    public function destroyAccount(Request $request, User $user): RedirectResponse
    {
        if (! $user->is(Auth::user())) {
            return redirect()
                ->route('shop.profile.edit')
                ->with('error', 'Liên kết này thuộc về một tài khoản khác.');
        }

        $request->validate([
            'xac_nhan' => ['required', 'string', 'in:'.AccountDeleter::CAU_XAC_NHAN],
        ], [
            'xac_nhan.required' => 'Hãy gõ đúng dòng xác nhận để tiếp tục.',
            'xac_nhan.in' => 'Dòng xác nhận chưa đúng. Hãy gõ chính xác: '
                .AccountDeleter::CAU_XAC_NHAN,
        ]);

        try {
            $this->deleter->delete($user);
        } catch (AccountDeletionException $e) {
            return back()->with('error', $e->getMessage());
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('welcome')
            ->with('success', 'Tài khoản của bạn đã được xoá. Cảm ơn bạn đã ghé ' . \App\Services\Shop\StoreProfile::name() . '.');
    }
}