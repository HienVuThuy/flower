<?php

namespace App\Services\Gift;

use App\Enums\GiftReturnRule;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductGift;
use App\Services\Refund\RefundService;

/** Quà khi trả hàng / đổi hàng — NƠI DUY NHẤT tính, theo luật của từng món quà. */
class GiftReturnCalculator
{
    public function __construct(
        private readonly RefundService $hoan,
    ) {
    }

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

    public function choDoi(OrderItem $item): bool
    {
        if (! $item->is_gift) {
            return true;
        }

        return $item->product_gift_id !== null
            && (bool) ProductGift::whereKey($item->product_gift_id)->value('cho_doi_hang');
    }

    private function soQuaTra(OrderItem $qua, OrderItem $cha, ?ProductGift $cauHinh, int $chaTraTongCong, int $quaDaTra): int
    {
        $daNhan = (int) $qua->quantity;
        $conGiu = max(0, (int) $cha->quantity - $chaTraTongCong);

        $duocHuong = $cauHinh !== null
            ? $cauHinh->soQuaCho($conGiu)
            : intdiv($daNhan * $conGiu, max(1, (int) $cha->quantity));

        return max(0, min($daNhan - $quaDaTra, $daNhan - min($daNhan, $duocHuong) - $quaDaTra));
    }
}
