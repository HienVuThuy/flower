<?php

namespace App\Services\Points;

use App\Enums\PointReason;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Time\Gio;
use Illuminate\Support\Facades\DB;

/** Chuỗi ngày ghé thăm — "chi phí chìm": đã giữ được 6 ngày thì ngày thứ 7 người ta quay lại để không mất chuỗi. */
class VisitStreak
{
    public function __construct(
        private readonly PointLedger $so,
    ) {
    }

    public static function moc(int $ngay): int
    {
        return match (true) {
            $ngay === 3 => 10,
            $ngay >= 7 && $ngay % 7 === 0 => 30,
            default => 0,
        };
    }

    public static function mocTiepTheo(int $chuoi): array
    {
        $ngay = $chuoi + 1;

        while (self::moc($ngay) === 0) {
            $ngay++;
        }

        return ['ngay' => $ngay, 'diem' => self::moc($ngay)];
    }

    public function ghiNhan(User $user): ?int
    {
        if ($user->role !== UserRole::Customer) {
            return null;
        }

        $homNay = now(Gio::mui())->toDateString();

        if ($user->last_visit_on?->toDateString() === $homNay) {
            return null;
        }

        $homQua = now(Gio::mui())->subDay()->toDateString();

        $chuoi = DB::transaction(function () use ($user, $homNay, $homQua) {
            $dong = DB::table('users')->where('id', $user->id)->lockForUpdate()->first(['visit_streak', 'last_visit_on']);

            if ($dong === null || $dong->last_visit_on === $homNay) {
                return null;
            }

            $moi = $dong->last_visit_on === $homQua ? (int) $dong->visit_streak + 1 : 1;

            DB::table('users')->where('id', $user->id)->update([
                'visit_streak' => $moi,
                'last_visit_on' => $homNay,
            ]);

            return $moi;
        });

        if ($chuoi === null) {
            $user->refresh();

            return null;
        }

        $user->forceFill(['visit_streak' => $chuoi, 'last_visit_on' => $homNay])->syncOriginal();

        $diem = self::moc($chuoi);

        if ($diem > 0) {
            $this->so->cong($user, $diem, PointReason::ChuoiNgay, 'chuoi:' . $homNay, 'Giữ chuỗi ' . $chuoi . ' ngày');
        }

        return $diem;
    }

    public function hienTai(User $user): int
    {
        $ngay = $user->last_visit_on?->toDateString();

        return in_array($ngay, [now(Gio::mui())->toDateString(), now(Gio::mui())->subDay()->toDateString()], true)
            ? (int) $user->visit_streak
            : 0;
    }
}
