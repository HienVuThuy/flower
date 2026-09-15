<?php

namespace App\Services\Gift;

use App\Enums\GiftReturnRule;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductGift;
use App\Services\Refund\RefundService;

/**
 * Quà khi trả hàng / đổi hàng — NƠI DUY NHẤT tính, theo luật của từng món quà.
 * ============================================================
 * TRẢ HÀNG: khách trả r món chính thì quà cần trả kèm =
 *     quà đã nhận − quà được hưởng với số món CÒN GIỮ − quà đã trả trước đó.
 * Ví dụ "mỗi 1 tặng 1, tối đa 2": mua 3, nhận 2 quà; trả 1 (còn giữ 2 →
 * vẫn được 2 quà) thì KHÔNG phải trả quà; trả 2 (còn 1 → được 1) thì trả 1.
 *
 * Luật "không thu hồi" → luôn 0. Cấu hình quà đã bị xoá → chia theo tỉ lệ
 * số món chính đã trả.
 *
 * CHỈ LÀ GIÁ TRỊ ĐIỀN SẴN: phiếu trả vẫn sửa được số quà (khách làm mất
 * quà, cửa hàng bỏ qua). Không trừ tiền.
 *
 * ĐỔI HÀNG: dòng quà chỉ đổi được khi món quà cho phép (cho_doi_hang).
 */
class GiftReturnCalculator
{
    public function __construct(
        private readonly RefundService $hoan,
    ) {
    }

    /**
     * Bảng điền sẵn cho từng dòng quà có món chính trong đơn.
     *
     * @return array<int, array{cha: int, quy_tac: string, bang: array<int, int>}>
     *               id dòng quà => [id dòng chính, luật, số món chính trả thêm => số quà điền sẵn]
     */
    public function bangTraKem(Order $order): array
    {
        $order->loadMissing('items');
        $ket = [];

        foreach ($order->items->where('is_gift', true)->whereNotNull('parent_item_id') as $qua) {
            $cha = $order->items->firstWhere('id', $qua->parent_item_id);

            if ($cha === null) {
                continue;
            }

            $cauHinh = $qua->product_gift_id ? ProductGift::find($qua->product_gift_id) : null;
            $quyTac = $cauHinh?->tra_hang ?? GiftReturnRule::KemQua;

            $chaDaTra = $this->hoan->soDaTra($cha);
            $chaCon = max(0, (int) $cha->quantity - $chaDaTra);
            $quaDaTra = $this->hoan->soDaTra($qua);

            $bang = [];

            for ($r = 0; $r <= $chaCon; $r++) {
                $bang[$r] = $quyTac === GiftReturnRule::KhongThuHoi
                    ? 0
                    : $this->soQuaTra($qua, $cha, $cauHinh, $chaDaTra + $r, $quaDaTra);
            }

            $ket[$qua->id] = ['cha' => $cha->id, 'quy_tac' => $quyTac->value, 'bang' => $bang];
        }

        return $ket;
    }

    /** Dòng hàng này có được đổi sang hàng khác không (xét riêng luật quà). */
    public function choDoi(OrderItem $item): bool
    {
        if (! $item->is_gift) {
            return true;
        }

        // Quà theo chương trình, hay quà kèm đã bị bỏ cấu hình: không đổi.
        return $item->product_gift_id !== null
            && (bool) ProductGift::whereKey($item->product_gift_id)->value('cho_doi_hang');
    }

    private function soQuaTra(OrderItem $qua, OrderItem $cha, ?ProductGift $cauHinh, int $chaTraTongCong, int $quaDaTra): int
    {
        $daNhan = (int) $qua->quantity;
        $conGiu = max(0, (int) $cha->quantity - $chaTraTongCong);

        $duocHuong = $cauHinh !== null
            ? $cauHinh->soQuaCho($conGiu)
            // Không còn cấu hình: giữ đúng tỉ lệ quà / món chính lúc nhận.
            : intdiv($daNhan * $conGiu, max(1, (int) $cha->quantity));

        return max(0, min($daNhan - $quaDaTra, $daNhan - min($daNhan, $duocHuong) - $quaDaTra));
    }
}
