<?php

namespace App\Services\Analytics;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/** Một khoảng thời gian báo cáo, và cách đọc giờ cho đúng. */
final class KhoangThoiGian
{
    public function __construct(
        public readonly ?Carbon $tu = null,
        public readonly ?Carbon $den = null,
    ) {
    }

    public static function muiGio(): string
    {
        return \App\Services\Time\Gio::mui();
    }

    public static function diaPhuong(CarbonInterface $moc): Carbon
    {
        return Carbon::instance($moc)->setTimezone(self::muiGio());
    }

    public static function nuaDemTruoc(int $soNgay): Carbon
    {
        return now(self::muiGio())
            ->subDays($soNgay)
            ->startOfDay()
            ->setTimezone((string) config('app.timezone'));
    }

    public function apDung(mixed $query, string $cot): mixed
    {
        if ($this->tu) {
            $query->where($cot, '>=', $this->tu);
        }

        if ($this->den) {
            $query->where($cot, '<', $this->den);
        }

        return $query;
    }

    public function apDungNgay(mixed $query, string $cot): mixed
    {
        if ($this->tu) {
            $query->where($cot, '>=', self::diaPhuong($this->tu)->toDateString());
        }

        if ($this->den) {
            $query->where($cot, '<', self::diaPhuong($this->den)->toDateString());
        }

        return $query;
    }
}
