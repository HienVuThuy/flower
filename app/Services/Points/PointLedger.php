<?php

namespace App\Services\Points;

use App\Enums\CouponType;
use App\Enums\PointReason;
use App\Enums\PromotionStatus;
use App\Models\Coupon;
use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Sổ điểm thưởng — NƠI DUY NHẤT cộng, trừ và đọc điểm. */
class PointLedger
{
    public const GOI = [
        'giam-20k' => ['diem' => 200, 'giam' => '20000', 'don_toi_thieu' => '150000', 'ngay' => 30],
        'giam-50k' => ['diem' => 450, 'giam' => '50000', 'don_toi_thieu' => '300000', 'ngay' => 30],
    ];

    public function soDu(User $user): int
    {
        return (int) PointTransaction::where('user_id', $user->id)->sum('amount');
    }

    public function lichSu(User $user, int $toiDa = 30): Collection
    {
        return PointTransaction::where('user_id', $user->id)
            ->latest('id')
            ->limit($toiDa)
            ->get();
    }

    public function cong(User $user, int $diem, PointReason $lyDo, string $khoa, ?string $ghiChu = null): bool
    {
        if ($diem <= 0) {
            throw new \InvalidArgumentException('Chỉ cộng số điểm dương; trừ điểm đi qua tru() hoặc doiVoucher().');
        }

        return $this->ghi($user, $diem, $lyDo, $khoa, $ghiChu);
    }

    public function tru(User $user, int $diem, PointReason $lyDo, string $khoa, ?string $ghiChu = null): bool
    {
        if ($diem <= 0) {
            throw new \InvalidArgumentException('Truyền số điểm cần trừ là số dương.');
        }

        return $this->ghi($user, -$diem, $lyDo, $khoa, $ghiChu);
    }

    public function dungChoDon(User $user, int $diem, \App\Models\Order $order): void
    {
        User::whereKey($user->id)->lockForUpdate()->first();

        if ($this->soDu($user) < $diem) {
            throw new PointException('Số điểm của bạn không còn đủ ' . number_format($diem, 0, ',', '.')
                . ' điểm — có thể vừa dùng ở nơi khác. Vui lòng chọn lại số điểm.');
        }

        $this->ghi($user, -$diem, PointReason::DungDiem, 'dung-diem:' . $order->id, 'Đơn ' . $order->order_number);
    }

    public function traDiemCuaDon(\App\Models\Order $order): int
    {
        $diem = (int) $order->points_used;

        if ($diem <= 0 || $order->user_id === null || ($user = User::find($order->user_id)) === null) {
            return 0;
        }

        if (! PointTransaction::where('user_id', $user->id)->where('source_key', 'dung-diem:' . $order->id)->exists()) {
            return 0;
        }

        return $this->ghi($user, $diem, PointReason::HoanDiem, 'tra-diem:' . $order->id, 'Trả điểm đơn ' . $order->order_number)
            ? $diem
            : 0;
    }

    private function ghi(User $user, int $soDiem, PointReason $lyDo, string $khoa, ?string $ghiChu): bool
    {
        try {
            (new PointTransaction())->forceFill([
                'user_id' => $user->id,
                'amount' => $soDiem,
                'reason' => $lyDo,
                'source_key' => $khoa,
                'note' => $ghiChu !== null ? Str::limit($ghiChu, 190) : null,
            ])->save();

            return true;
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return false;
            }

            throw $e;
        }
    }

    public function doiVoucher(User $user, string $maGoi): Coupon
    {
        $goi = self::GOI[$maGoi] ?? throw new PointException('Gói đổi điểm không tồn tại.');

        return DB::transaction(function () use ($user, $goi) {
            User::whereKey($user->id)->lockForUpdate()->first();

            if ($this->soDu($user) < $goi['diem']) {
                throw new PointException('Bạn chưa đủ ' . $goi['diem'] . ' điểm để đổi gói này.');
            }

            $coupon = new Coupon([
                'code' => $this->sinhMa(),
                'name' => 'Đổi ' . $goi['diem'] . ' điểm',
                'description' => 'Voucher đổi từ điểm thưởng — chỉ tài khoản đã đổi dùng được.',
                'type' => CouponType::FixedAmount,
                'value' => $goi['giam'],
                'min_order_amount' => $goi['don_toi_thieu'],
                'usage_limit' => 1,
                'per_user_limit' => 1,
                'starts_at' => now(),
                'ends_at' => now()->addDays($goi['ngay']),
                'status' => PromotionStatus::Active,
                'is_public' => false,
            ]);
            $coupon->forceFill(['owner_user_id' => $user->id])->save();

            DB::table('coupon_user')->insert([
                'user_id' => $user->id,
                'coupon_id' => $coupon->id,
                'claimed_at' => now(),
                'used_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            (new PointTransaction())->forceFill([
                'user_id' => $user->id,
                'amount' => -$goi['diem'],
                'reason' => PointReason::DoiVoucher,
                'source_key' => 'voucher:' . $coupon->id,
                'coupon_id' => $coupon->id,
                'note' => 'Mã ' . $coupon->code,
            ])->save();

            return $coupon;
        });
    }

    private function sinhMa(): string
    {
        do {
            $ma = 'DIEM-' . substr(str_shuffle(str_repeat('ABCDEFGHJKLMNPQRSTUVWXYZ23456789', 3)), 0, 8);
        } while (Coupon::where('code', $ma)->exists());

        return $ma;
    }
}
