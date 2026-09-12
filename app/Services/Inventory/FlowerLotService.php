<?php

namespace App\Services\Inventory;

use App\Enums\FlowerLotStatus;
use App\Enums\FlowerQuality;
use App\Models\FlowerLot;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lập và đóng lô hoa.
 * ============================================================
 * ĐÓNG LÔ LÀ HÀNH ĐỘNG DUY NHẤT BIẾN TIỀN MUA HOA THÀNH GIÁ VỐN.
 *
 * Lô còn mở nghĩa là hoa vẫn còn trong xô — tiền đã trả nhưng chưa bán
 * hết. Chỉ khi người bán nói "lô này hết rồi" thì toàn bộ tiền của lô
 * mới thuộc về kỳ đó.
 *
 * Hệ quả phải nói ra với người dùng: **quên đóng lô là giá vốn hoa thấp
 * hơn sự thật**, và lãi gộp hoa cao hơn sự thật. Không có cách nào máy
 * tự biết lô đã hết — nên trang danh sách phải nhắc những lô mở quá lâu.
 */
class FlowerLotService
{
    /**
     * Lô mở quá số ngày này thì gần như chắc chắn đã hết mà quên đóng.
     *
     * Hoa tươi giữ được 3–7 ngày tuỳ loại. Mười ngày là mốc rộng rãi:
     * nhắc sớm quá thì người ta học cách lờ lời nhắc đi.
     */
    public const NGAY_NHAC_DONG = 10;

    public function __construct(
        private readonly ActivityLogger $audit,
    ) {
    }

    /**
     * Đóng lô: ghi hao hụt, chất lượng, và chốt tiền vào kỳ.
     *
     * @throws FlowerLotException
     */
    public function dongLo(FlowerLot $lo, float|string $haoHut = 0, ?string $chatLuong = null, ?string $ghiChu = null): void
    {
        DB::transaction(function () use ($lo, $haoHut, $chatLuong, $ghiChu) {
            /*
             * KHOÁ RỒI ĐỌC LẠI.
             *
             * Đóng hai lần thì `closed_at` bị đẩy sang kỳ khác, và giá
             * vốn hoa nhảy từ kỳ này sang kỳ kia mà không ai thấy. Bấm
             * hai lần vì trang chậm là đủ để tái hiện.
             */
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

            /*
             * HAO HỤT KHÔNG VƯỢT QUÁ SỐ ĐÃ MUA.
             *
             * Hao 12 bó trên một lô 10 bó là một con số không có nghĩa,
             * và nó sẽ đi thẳng vào bảng so sánh chất lượng nhà cung cấp
             * dưới dạng "hao hụt 120%".
             */
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

    /** Lô còn mở quá lâu — gần như chắc chắn đã hết mà quên đóng. */
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
