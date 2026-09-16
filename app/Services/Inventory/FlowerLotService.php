<?php

namespace App\Services\Inventory;

use App\Enums\FlowerLotStatus;
use App\Enums\FlowerQuality;
use App\Models\FlowerLot;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Lập và đóng lô hoa. */
class FlowerLotService
{
    public const NGAY_NHAC_DONG = 10;

    public function __construct(
        private readonly ActivityLogger $audit,
    ) {
    }

    public function dongLo(FlowerLot $lo, float|string $haoHut = 0, ?string $chatLuong = null, ?string $ghiChu = null): void
    {
        DB::transaction(function () use ($lo, $haoHut, $chatLuong, $ghiChu) {
            $khoa = FlowerLot::whereKey($lo->id)->lockForUpdate()->first();

            if (! $khoa) {
                throw new FlowerLotException('Không tìm thấy lô hoa.');
            }

            if ($khoa->status === FlowerLotStatus::DaDong) {
                throw new FlowerLotException('Lô này đã đóng rồi.');
            }

            $hao = bcadd((string) $haoHut, '0', 2);

            if (bccomp($hao, '0', 2) < 0) {
                throw new FlowerLotException('Hao hụt không thể là số âm.');
            }

            if (bccomp($hao, (string) $khoa->quantity, 2) > 0) {
                throw new FlowerLotException(sprintf(
                    'Hao hụt (%s) không thể lớn hơn số đã mua (%s %s).',
                    $hao,
                    $khoa->quantity,
                    $khoa->unit->label(),
                ));
            }

            $khoa->forceFill([
                'hao_hut' => $hao,
                'quality' => $chatLuong ? FlowerQuality::from($chatLuong) : $khoa->quality,
                'note' => $ghiChu !== null && trim($ghiChu) !== '' ? trim($ghiChu) : $khoa->note,
                'status' => FlowerLotStatus::DaDong,
                'closed_at' => now(),
            ])->save();
        });

        $lo->refresh();

        $this->audit->log(
            'kho.dong-lo-hoa',
            sprintf('Đóng lô hoa %s: hao hụt %s %s', $lo->code, $lo->hao_hut, $lo->unit->label()),
            $lo,
            ['code' => $lo->code, 'hao_hut' => (string) $lo->hao_hut],
        );
    }

    public function capNhatLo(FlowerLot $lo, array $data): void
    {
        $truoc = [];

        DB::transaction(function () use ($lo, $data, &$truoc) {
            $khoa = $this->khoaLoConSuaDuoc($lo);

            $truoc = $khoa->only(array_keys($data));

            $khoa->forceFill($data)->save();
        });

        $lo->refresh();

        $doi = [];

        foreach ($truoc as $truong => $cu) {
            $moi = $lo->getAttribute($truong);
            $cuChu = $cu instanceof \BackedEnum ? $cu->value : (string) $cu;
            $moiChu = $moi instanceof \BackedEnum ? $moi->value : (string) $moi;

            if ($cu instanceof \DateTimeInterface) {
                $cuChu = $cu->format('Y-m-d');
                $moiChu = $moi?->format('Y-m-d') ?? '';
            }

            if ($cuChu !== $moiChu) {
                $doi[$truong] = $cuChu . ' → ' . $moiChu;
            }
        }

        $this->audit->log(
            'kho.sua-lo-hoa',
            sprintf('Sửa lô hoa %s', $lo->code),
            $lo,
            ['code' => $lo->code] + $doi,
        );
    }

    public function xoaLo(FlowerLot $lo): void
    {
        $banChup = [];

        DB::transaction(function () use ($lo, &$banChup) {
            $khoa = $this->khoaLoConSuaDuoc($lo);

            $banChup = [
                'code' => $khoa->code,
                'total_cost' => (string) $khoa->total_cost,
                'quantity' => (string) $khoa->quantity . ' ' . $khoa->unit->value,
            ];

            $khoa->delete();
        });

        $this->audit->log(
            'kho.xoa-lo-hoa',
            sprintf('Xoá lô hoa %s (%s đ)', $banChup['code'], $banChup['total_cost']),
            null,
            $banChup,
        );
    }

    public static function conSuaDuoc(FlowerLot $lo): bool
    {
        return ! $lo->daDong() && ! $lo->daTraLai();
    }

    private function khoaLoConSuaDuoc(FlowerLot $lo): FlowerLot
    {
        $khoa = FlowerLot::whereKey($lo->id)->lockForUpdate()->first();

        if (! $khoa) {
            throw new FlowerLotException('Không tìm thấy lô hoa.');
        }

        if ($khoa->daDong()) {
            throw new FlowerLotException(
                'Lô ' . $khoa->code . ' đã đóng — tiền đã vào giá vốn của một kỳ nên không sửa hay xoá được.'
            );
        }

        if ($khoa->daTraLai()) {
            throw new FlowerLotException(
                'Lô ' . $khoa->code . ' đã ghi trả hàng cho vựa — tiền lấy lại tính theo đơn giá cũ, sửa lô là lệch.'
            );
        }

        return $khoa;
    }

    public function loQuenDong(): \Illuminate\Database\Eloquent\Collection
    {
        return FlowerLot::query()
            ->dangDung()
            ->where('purchased_at', '<', now()->subDays(self::NGAY_NHAC_DONG)->toDateString())
            ->with('kind')
            ->orderBy('purchased_at')
            ->get();
    }

    public function sinhMa(): string
    {
        for ($lan = 0; $lan < 5; $lan++) {
            $ma = sprintf('LH-%s-%s', now()->format('ymd'), Str::upper(Str::random(4)));

            if (! FlowerLot::where('code', $ma)->exists()) {
                return $ma;
            }
        }

        throw new FlowerLotException('Không sinh được mã lô, vui lòng thử lại.');
    }
}
