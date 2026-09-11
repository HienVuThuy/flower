<?php

namespace App\Services\Inventory;

use App\Enums\StockCountStatus;
use App\Models\StockCount;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lập và ghi sổ phiếu kiểm kê.
 * ============================================================
 * BA LUẬT, cùng tinh thần với phiếu nhập:
 *
 *   1. TỒN HỆ THỐNG CHỤP Ở MÁY CHỦ, không nhận từ biểu mẫu. Con số "hệ thống
 *      đang ghi" trên màn hình có thể đã cũ; và một con số gửi lên từ trình
 *      duyệt thì ai cũng sửa được để chênh lệch ra bằng bao nhiêu tuỳ ý.
 *
 *   2. GHI SỔ CỘNG CHÊNH LỆCH, không gán số đếm (xem migration).
 *
 *   3. KHÔNG ĐỂ TỒN ÂM. Chênh lệch cộng vào mà ra số âm nghĩa là giữa lúc đếm
 *      và lúc ghi sổ đã có đơn bán mà hàng thật không còn — sổ sách mâu
 *      thuẫn. Kẹp về 0 là bịa một con số; từ chối và yêu cầu đếm lại.
 */
class StockCountService
{
    public function __construct(
        private readonly StockAdjuster $kho,
        private readonly StockUnits $donVi,
        private readonly ActivityLogger $audit,
    ) {
    }

    /**
     * Lập phiếu nháp từ những dòng người dùng đã nhập số đếm.
     *
     * @param  array<string, array{counted?: mixed, reason?: mixed}>  $dem  khoá là "idSảnPhẩm:idQuyCách"
     *
     * @throws InventoryException
     */
    public function lap(array $dem, string $ngayDem, ?string $ghiChu): StockCount
    {
        /*
         * CHỈ NHẬN KHOÁ CÓ TRONG DANH SÁCH ĐƠN VỊ KHO THẬT. Khoá lạ từ biểu mẫu
         * (sản phẩm không theo dõi tồn, id bịa) bị bỏ, không đưa thẳng vào
         * khoá ngoại.
         */
        $donVi = $this->donVi->danhSach()->keyBy('value');

        $dong = [];

        foreach ($dem as $khoa => $d) {
            $so = $d['counted'] ?? null;

            // Để trống = không đếm món này. Khác với đếm được 0.
            if ($so === null || $so === '') {
                continue;
            }

            $dv = $donVi->get((string) $khoa);

            if (! $dv) {
                continue;
            }

            $dong[] = [
                'product_id' => $dv['product_id'],
                'product_variant_id' => $dv['variant_id'],
                'product_name' => $dv['ten'],
                'variant_name' => $dv['quy_cach'],
                'system_quantity' => $dv['ton'],
                'counted_quantity' => max(0, (int) $so),
                'reason' => trim((string) ($d['reason'] ?? '')) ?: null,
            ];
        }

        if ($dong === []) {
            throw new InventoryException('Chưa nhập số đếm cho mặt hàng nào. Để trống nghĩa là không đếm món đó.');
        }

        return DB::transaction(function () use ($dong, $ngayDem, $ghiChu) {
            $phieu = StockCount::create([
                'code' => $this->sinhMa(),
                'note' => $ghiChu,
                'counted_at' => $ngayDem,
            ]);

            $phieu->forceFill([
                'created_by' => Auth::id(),
                'created_by_name' => Auth::user()?->name,
            ])->save();

            $phieu->items()->createMany($dong);

            return $phieu;
        });
    }

    /**
     * Ghi sổ: cộng chênh lệch từng dòng vào kho, cả phiếu một transaction.
     *
     * @throws InventoryException
     */
    public function ghiSo(StockCount $phieu): void
    {
        DB::transaction(function () use ($phieu) {
            $khoa = StockCount::whereKey($phieu->id)->lockForUpdate()->first();

            if (! $khoa) {
                throw new InventoryException('Không tìm thấy phiếu kiểm kê.');
            }

            if ($khoa->status === StockCountStatus::Posted) {
                throw new InventoryException('Phiếu này đã ghi sổ rồi.');
            }

            foreach ($khoa->items as $dong) {
                $chenh = $dong->chenhLech();

                $this->kho->dieuChinh(
                    $dong->product_variant_id,
                    $dong->product_id,
                    $chenh,
                    $dong->product_name . ($dong->variant_name ? ' — ' . $dong->variant_name : ''),
                    khongDuocAm: true,
                );

                $dong->forceFill(['applied_difference' => $chenh])->save();
            }

            $khoa->forceFill([
                'status' => StockCountStatus::Posted,
                'posted_at' => now(),
            ])->save();
        });

        $phieu->refresh()->load('items');

        $this->audit->log(
            'kho.ghi-so-kiem-ke',
            sprintf(
                'Ghi sổ phiếu kiểm kê %s: %d dòng, %d dòng lệch, tổng chênh %+d',
                $phieu->code,
                $phieu->items->count(),
                $phieu->soDongLech(),
                $phieu->items->sum(fn ($i) => $i->chenhLech()),
            ),
            $phieu,
            ['code' => $phieu->code],
        );
    }

    /** KK-260930-A3F2. */
    private function sinhMa(): string
    {
        for ($lan = 0; $lan < 5; $lan++) {
            $ma = sprintf('KK-%s-%s', now()->format('ymd'), Str::upper(Str::random(4)));

            if (! StockCount::where('code', $ma)->exists()) {
                return $ma;
            }
        }

        throw new InventoryException('Không sinh được mã phiếu, vui lòng thử lại.');
    }
}
