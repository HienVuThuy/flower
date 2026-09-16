<?php

namespace App\Services\Auth;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Mail\AccountDeletionMail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/** Xoá tài khoản theo yêu cầu của chính chủ. */
class AccountDeleter
{
    public const TTL_MINUTES = 30;

    public const CAU_XAC_NHAN = 'XOA TAI KHOAN';

    public function sendConfirmation(User $user): void
    {
        $this->assertDeletable($user);

        $url = URL::temporarySignedRoute(
            'shop.profile.delete.confirm',
            now()->addMinutes(self::TTL_MINUTES),
            ['user' => $user->id],
        );

        Mail::to($user->email)->send(
            new AccountDeletionMail($user, $url, self::TTL_MINUTES)
        );

        Log::info('Đã gửi thư xác nhận xoá tài khoản.', ['user_id' => $user->id]);
    }

    public function delete(User $user): void
    {
        $this->assertDeletable($user);

        DB::transaction(function () use ($user) {
            if (Auth::check() && Auth::id() === $user->id) {
                Auth::guard('web')->logout();
            }

            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();

            $user->delete();
        });

        Log::info('Tài khoản đã bị xoá theo yêu cầu của chủ tài khoản.');
    }

    public function summary(User $user): array
    {
        return [
            'donGiuLai' => Order::where('user_id', $user->id)->count(),
            'danhGiaXoa' => $user->reviews()->count(),
            'diaChiXoa' => $user->addresses()->count(),
            'yeuThichXoa' => $user->wishlists()->count(),
        ];
    }

    private function assertDeletable(User $user): void
    {
        if ($user->role === UserRole::Admin) {
            $conLai = User::where('role', UserRole::Admin)
                ->whereNull('locked_at')
                ->whereKeyNot($user->getKey())
                ->count();

            if ($conLai === 0) {
                throw new AccountDeletionException(
                    'Đây là tài khoản quản trị duy nhất còn hoạt động nên không thể xoá. '
                    .'Hãy chỉ định một quản trị viên khác trước.'
                );
            }
        }

        $dangDo = Order::where('user_id', $user->id)
            ->whereNotIn('status', [
                OrderStatus::Completed->value,
                OrderStatus::Cancelled->value,
            ])
            ->count();

        if ($dangDo > 0) {
            throw new AccountDeletionException(sprintf(
                'Bạn còn %d đơn hàng chưa hoàn tất. Hãy đợi giao xong hoặc huỷ đơn trước khi '
                .'xoá tài khoản — sau khi xoá, bạn sẽ không còn chỗ nào để theo dõi đơn đó.',
                $dangDo,
            ));
        }
    }
}
