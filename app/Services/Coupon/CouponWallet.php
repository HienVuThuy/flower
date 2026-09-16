<?php

namespace App\Services\Coupon;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Ví voucher: mã giảm giá khách đã lưu về tài khoản. */
class CouponWallet
{
    public function claim(User $user, Coupon $coupon): bool
    {
        if (! $coupon->is_public) {
            throw new CouponException('Mã này không nằm trong chương trình đang mở.');
        }

        if (! $coupon->isRunning()) {
            throw new CouponException('Mã giảm giá không còn hiệu lực.');
        }

        if ($coupon->isExhausted()) {
            throw new CouponException('Mã giảm giá đã hết lượt.');
        }

        try {
            DB::table('coupon_user')->insert([
                'user_id' => $user->id,
                'coupon_id' => $coupon->id,
                'claimed_at' => now(),
                'used_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return false;
            }

            throw $e;
        }
    }

    public function discard(User $user, Coupon $coupon): ?string
    {
        $row = DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->first(['used_count']);

        if ($row === null) {
            return null;
        }

        if ((int) $row->used_count === 0) {
            DB::table('coupon_user')
                ->where('user_id', $user->id)
                ->where('coupon_id', $coupon->id)
                ->delete();

            return 'deleted';
        }

        DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->update(['hidden_at' => now(), 'updated_at' => now()]);

        return 'hidden';
    }

    public function unhide(User $user, Coupon $coupon): bool
    {
        return DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->whereNotNull('hidden_at')
            ->update(['hidden_at' => null, 'updated_at' => now()]) > 0;
    }

    public function conDungDuoc(array $row): bool
    {
        $coupon = $row['coupon'];

        return ! $row['exhaustedForUser']
            && $coupon->isRunning()
            && ! $coupon->isExhausted();
    }

    public function hiddenCount(?User $user): int
    {
        return $user === null ? 0 : DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->whereNotNull('hidden_at')
            ->count();
    }

    public function forUser(User $user, bool $daAn = false): Collection
    {
        $rows = DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->when($daAn, fn ($q) => $q->whereNotNull('hidden_at'))
            ->when(! $daAn, fn ($q) => $q->whereNull('hidden_at'))
            ->orderByDesc('claimed_at')
            ->get(['coupon_id', 'used_count']);

        if ($rows->isEmpty()) {
            return collect();
        }

        $coupons = Coupon::whereIn('id', $rows->pluck('coupon_id'))->get()->keyBy('id');

        return $rows
            ->map(function ($row) use ($coupons) {
                $coupon = $coupons->get($row->coupon_id);

                return $coupon === null ? null : [
                    'coupon' => $coupon,
                    'usedCount' => (int) $row->used_count,
                    'exhaustedForUser' => $this->limitReached($coupon, (int) $row->used_count),
                ];
            })
            ->filter()
            ->sortBy(fn (array $row) => $row['coupon']->ends_at?->getTimestamp() ?? PHP_INT_MAX)
            ->values();
    }

    public function claimableFor(?User $user): Collection
    {
        $query = Coupon::query()
            ->where('is_public', true)
            ->whereNull('promotion_id')
            ->usableNow()
            ->orderByDesc('value');

        if ($user) {
            $claimed = DB::table('coupon_user')
                ->where('user_id', $user->id)
                ->pluck('coupon_id');

            if ($claimed->isNotEmpty()) {
                $query->whereNotIn('id', $claimed);
            }
        }

        return $query->get()
            ->reject(fn (Coupon $c) => $c->isExhausted())
            ->values();
    }

    public function has(?User $user, Coupon $coupon): bool
    {
        return $user !== null && DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->exists();
    }

    public function userLimitReached(?User $user, Coupon $coupon): bool
    {
        if ($user === null || $coupon->per_user_limit === null) {
            return false;
        }

        $used = (int) DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->value('used_count');

        return $this->limitReached($coupon, $used);
    }

    public function markUsed(?User $user, Coupon $coupon): void
    {
        if ($user === null) {
            return;
        }

        DB::table('coupon_user')->insertOrIgnore([
            'user_id' => $user->id,
            'coupon_id' => $coupon->id,
            'claimed_at' => now(),
            'used_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $gioiHan = \App\Models\Coupon::whereKey($coupon->id)->value('per_user_limit');

        $ghiNhan = DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->when($gioiHan !== null, fn ($q) => $q->where('used_count', '<', $gioiHan))
            ->update(['used_count' => DB::raw('used_count + 1'), 'updated_at' => now()]);

        if ($ghiNhan === 0) {
            throw new CouponException(sprintf(
                'Tài khoản của bạn đã dùng hết số lần cho mã %s.',
                $coupon->code,
            ));
        }
    }

    public function releaseUse(?User $user, Coupon $coupon): void
    {
        if ($user === null) {
            return;
        }

        DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->update([
                'used_count' => DB::raw('GREATEST(used_count - 1, 0)'),
                'updated_at' => now(),
            ]);
    }

    private function limitReached(Coupon $coupon, int $used): bool
    {
        return $coupon->per_user_limit !== null && $used >= $coupon->per_user_limit;
    }
}
