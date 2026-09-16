<?php

namespace App\Services\Analytics;

use App\Services\Time\Gio;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Kỳ mà admin đang xem: một mốc dựng sẵn, hoặc một khoảng ngày tự chọn. */
final class ChonKy
{
    public const TUY_CHON = 'tuy-chon';

    private function __construct(
        public readonly string $ma,
        public readonly ?Carbon $tu = null,
        public readonly ?Carbon $den = null,
    ) {
    }

    public static function tuRequest(Request $request): self
    {
        return self::tuThamSo(
            $request->query('ky'),
            $request->query('tu'),
            $request->query('den'),
        );
    }

    public static function tuThamSo(mixed $ky, mixed $tu = null, mixed $den = null): self
    {
        $a = self::ngay($tu);
        $b = self::ngay($den);

        if ($a === null || $b === null) {
            return new self(AnalyticsService::hopLeKy($ky));
        }

        if ($b->lessThan($a)) {
            [$a, $b] = [$b, $a];
        }

        $luu = Gio::muiLuu();

        return new self(
            self::TUY_CHON,
            $a->copy()->startOfDay()->setTimezone($luu),
            $b->copy()->addDay()->startOfDay()->setTimezone($luu),
        );
    }

    public function laTuyChon(): bool
    {
        return $this->ma === self::TUY_CHON;
    }

    public function apDung(AnalyticsService $analytics): AnalyticsService
    {
        return $this->laTuyChon()
            ? $analytics->forRange($this->tu, $this->den)
            : $analytics->forPeriod($this->ma);
    }

    public function nhan(): string
    {
        if (! $this->laTuyChon()) {
            return AnalyticsService::PERIODS[$this->ma];
        }

        return Gio::hien($this->tu)->format('d/m/Y')
            . ' – '
            . Gio::hien($this->den)->subDay()->format('d/m/Y');
    }

    public function khoangHienThi(): ?string
    {
        if ($this->laTuyChon()) {
            return $this->nhan();
        }

        $soNgay = match ($this->ma) {
            '7' => 7,
            '30' => 30,
            default => null,
        };

        if ($soNgay === null) {
            return null;
        }

        $tu = now(Gio::mui())->subDays($soNgay - 1)->startOfDay();

        return $tu->format('d/m/Y') . ' – ' . now(Gio::mui())->format('d/m/Y H:i');
    }

    public function thamSo(): array
    {
        if (! $this->laTuyChon()) {
            return ['ky' => $this->ma];
        }

        return [
            'ky' => self::TUY_CHON,
            'tu' => $this->oTu(),
            'den' => $this->oDen(),
        ];
    }

    public function oTu(): ?string
    {
        return $this->tu ? Gio::hien($this->tu)->format('Y-m-d') : null;
    }

    public function oDen(): ?string
    {
        return $this->den ? Gio::hien($this->den)->subDay()->format('Y-m-d') : null;
    }

    private static function ngay(mixed $gt): ?Carbon
    {
        if (! is_string($gt) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $gt)) {
            return null;
        }

        $ngay = Carbon::createFromFormat('!Y-m-d', $gt, Gio::mui());

        return ($ngay !== false && $ngay->format('Y-m-d') === $gt) ? $ngay : null;
    }
}
