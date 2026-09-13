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

    /**
     * Sửa một lô CÒN MỞ — cho lỗi gõ nhầm lúc ghi.
     * ============================================================
     * VÌ SAO PHẢI CHO SỬA: gõ nhầm 5.000.000 thành 50.000.000 mà không sửa
     * được thì con số đó đi thẳng vào giá vốn khi đóng lô. Phiếu nhập nháp
     * xoá được; lô mở cũng phải gỡ được.
     *
     * HAI CHỖ CHẶN, cả hai đọc lại SAU KHI KHOÁ:
     *   - Lô đã đóng: tiền đã vào giá vốn của một kỳ — sửa là sửa lại một
     *     báo cáo đã đọc.
     *   - Lô đã ghi trả hàng: tiền lấy lại được tính theo đơn giá CŨ. Đổi
     *     số lượng hay tổng tiền là tiền trả lại không còn khớp với lô.
     *
     * @param  array<string, mixed>  $data  đã validate, kèm supplier_name bản chụp
     *
     * @throws FlowerLotException
     */
    public function capNhatLo(FlowerLot $lo, array $data): void
    {
        $truoc = [];

        DB::transaction(function () use ($lo, $data, &$truoc) {
            $khoa = $this->khoaLoConSuaDuoc($lo);

            $truoc = $khoa->only(array_keys($data));

            $khoa->forceFill($data)->save();
        });

        $lo->refresh();

        // Chỉ ghi những trường THẬT SỰ đổi, dạng "trước → sau": nhật ký
        // phải trả lời được "ai đổi tổng tiền lô này từ bao nhiêu".
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

    /**
     * Xoá một lô CÒN MỞ — cho lô ghi nhầm hẳn (ghi trùng hai lần).
     *
     * Cùng hai chỗ chặn với capNhatLo(). Có ghi nhật ký kèm số tiền: xoá
     * một lô 3.000.000 mà không để lại dấu vết thì không ai đối chiếu được.
     *
     * @throws FlowerLotException
     */
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

    /** Lô còn sửa/xoá được không — để giao diện chỉ hiện nút khi bấm được. */
    public static function conSuaDuoc(FlowerLot $lo): bool
    {
        return ! $lo->daDong() && ! $lo->daTraLai();
    }

    /**
     * Khoá dòng, đọc lại, và từ chối nếu lô không còn sửa được.
     *
     * @throws FlowerLotException
     */
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
