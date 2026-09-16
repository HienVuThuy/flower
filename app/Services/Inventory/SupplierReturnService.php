<?php

namespace App\Services\Inventory;

use App\Enums\ReturnReason;
use App\Enums\ReturnSettlement;
use App\Enums\StockReceiptKind;
use App\Enums\StockReceiptStatus;
use App\Models\FlowerLot;
use App\Models\StockReceipt;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Trả hàng lại cho nhà cung cấp. */
class SupplierReturnService
{
    public function __construct(
        private readonly StockReceiptService $phieu,
        private readonly ActivityLogger $audit,
    ) {
    }

    public function traHangDem(StockReceipt $goc, array $dong, array $data): StockReceipt
    {
        $lyDo = ReturnReason::from((string) $data['reason']);
        $cach = ReturnSettlement::from((string) $data['settlement']);

        return DB::transaction(function () use ($goc, $dong, $data, $lyDo, $cach) {
            $khoa = StockReceipt::whereKey($goc->id)->lockForUpdate()->first();

            if (! $khoa) {
                throw new StockReceiptException('Không tìm thấy phiếu nhập gốc.');
            }

            if ($khoa->status !== StockReceiptStatus::Posted) {
                throw new StockReceiptException(
                    'Phiếu gốc chưa ghi sổ nên chưa có gì để trả. Sửa hoặc xoá phiếu nháp đó thay vì lập phiếu trả.'
                );
            }

            if ($khoa->kind !== StockReceiptKind::NhapMoi) {
                throw new StockReceiptException('Chỉ trả lại hàng của phiếu nhập mới.');
            }

            $khoa->load('items');

            $dongTra = $this->dongTraHangDem($khoa, $dong);

            if ($dongTra === []) {
                throw new StockReceiptException('Chưa chọn món nào để trả.');
            }

            $tien = $this->tienTraLai($cach, $data['settlement_amount'] ?? null, $dongTra);

            $dongTra = $this->chiaTienVeCacDong($dongTra, $tien);

            $phieuTra = StockReceipt::create([
                'code' => $this->phieu->sinhMa(),
                'supplier_id' => $khoa->supplier_id,
                'supplier' => $khoa->supplier,
                'note' => trim((string) ($data['note'] ?? '')) ?: null,
                'received_at' => $data['returned_at'],
            ]);

            $phieuTra->forceFill([
                'kind' => StockReceiptKind::TraNcc,
                'return_reason' => $lyDo,
                'settlement' => $cach,
                'settlement_amount' => $tien,
                'return_of_id' => $khoa->id,
            ])->save();

            $this->phieu->gan($phieuTra);

            foreach ($dongTra as $d) {
                $phieuTra->items()->create($d);
            }

            return $phieuTra->fresh('items');
        });
    }

    public function traHangHoa(FlowerLot $lo, array $data): void
    {
        $lyDo = ReturnReason::from((string) $data['reason']);
        $cach = ReturnSettlement::from((string) $data['settlement']);

        DB::transaction(function () use ($lo, $data, $lyDo, $cach) {
            $khoa = FlowerLot::whereKey($lo->id)->lockForUpdate()->first();

            if (! $khoa) {
                throw new FlowerLotException('Không tìm thấy lô hoa.');
            }

            if ($khoa->tra_lai_qty !== null) {
                throw new FlowerLotException('Lô này đã ghi trả hàng rồi.');
            }

            $sl = bcadd((string) $data['quantity'], '0', 2);

            if (bccomp($sl, '0', 2) <= 0) {
                throw new FlowerLotException('Số lượng trả phải lớn hơn 0.');
            }

            if (bccomp($sl, (string) $khoa->quantity, 2) > 0) {
                throw new FlowerLotException(sprintf(
                    'Chỉ trả được tối đa %s %s — đúng bằng số đã lấy.',
                    $khoa->quantity,
                    $khoa->unit->label(),
                ));
            }

            $donGia = $khoa->donGia() ?? '0.00';
            $tien = $cach->tienQuayVe() ? bcmul($donGia, $sl, 2) : null;

            $khoa->forceFill([
                'tra_lai_qty' => $sl,
                'tra_lai_tien' => $tien,
                'tra_lai_ly_do' => $lyDo,
                'tra_lai_settlement' => $cach,
                'tra_lai_at' => now(),
                'note' => trim((string) ($data['note'] ?? '')) ?: $khoa->note,
            ])->save();
        });

        $lo->refresh();

        $this->audit->log(
            'kho.tra-hang-lo-hoa',
            sprintf('Trả %s %s của lô %s', $lo->tra_lai_qty, $lo->unit->label(), $lo->code),
            $lo,
            ['code' => $lo->code, 'so_luong' => (string) $lo->tra_lai_qty],
        );
    }

    private function dongTraHangDem(StockReceipt $goc, array $dong): array
    {
        $ket = [];

        foreach ($dong as $idDong => $d) {
            $sl = (int) ($d['quantity'] ?? 0);

            if ($sl <= 0) {
                continue;
            }

            $item = $goc->items->firstWhere('id', (int) $idDong);

            if (! $item) {
                throw new StockReceiptException('Có dòng hàng không thuộc phiếu nhập này.');
            }

            $con = (int) $item->quantity - $this->soDaTra($item->id);

            if ($sl > $con) {
                throw new StockReceiptException(sprintf(
                    '"%s" chỉ còn trả được %d.',
                    $item->product_name,
                    max(0, $con),
                ));
            }

            $ket[] = [
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,

                'quantity' => -$sl,

                'unit_cost' => $item->unit_cost,
            ];
        }

        return $ket;
    }

    private function chiaTienVeCacDong(array $dongTra, ?string $tien): array
    {
        $nen = '0.00';

        foreach ($dongTra as $d) {
            if ($d['unit_cost'] === null) {
                continue;
            }

            $nen = bcadd($nen, bcmul((string) $d['unit_cost'], (string) abs((int) $d['quantity']), 2), 2);
        }

        foreach ($dongTra as $i => $d) {
            if ($d['unit_cost'] === null) {
                continue;
            }

            if ($tien === null || bccomp($nen, '0', 2) <= 0) {
                $dongTra[$i]['unit_cost'] = '0.00';

                continue;
            }

            $sl = (string) abs((int) $d['quantity']);

            $phan = bcdiv(bcmul($tien, bcmul((string) $d['unit_cost'], $sl, 2), 4), $nen, 4);

            $dongTra[$i]['unit_cost'] = bcdiv($phan, $sl, 2);
        }

        return $dongTra;
    }

    public function soDaTra(int $idDongGoc): int
    {
        $goc = \App\Models\StockReceiptItem::find($idDongGoc);

        if (! $goc) {
            return 0;
        }

        return (int) abs((float) \App\Models\StockReceiptItem::query()
            ->join('stock_receipts', 'stock_receipts.id', '=', 'stock_receipt_items.stock_receipt_id')
            ->where('stock_receipts.kind', StockReceiptKind::TraNcc->value)
            ->where('stock_receipts.return_of_id', $goc->stock_receipt_id)
            ->where('stock_receipt_items.product_id', $goc->product_id)
            ->when(
                $goc->product_variant_id,
                fn ($q) => $q->where('stock_receipt_items.product_variant_id', $goc->product_variant_id),
                fn ($q) => $q->whereNull('stock_receipt_items.product_variant_id'),
            )
            ->sum('stock_receipt_items.quantity'));
    }

    private function tienTraLai(ReturnSettlement $cach, mixed $goNhap, array $dongTra): ?string
    {
        if (! $cach->tienQuayVe()) {
            return null;
        }

        if ($goNhap !== null && $goNhap !== '') {
            return bcadd((string) (int) $goNhap, '0', 2);
        }

        $tong = '0.00';

        foreach ($dongTra as $d) {
            if ($d['unit_cost'] === null) {
                continue;
            }

            $tong = bcadd($tong, bcmul((string) $d['unit_cost'], (string) abs((int) $d['quantity']), 2), 2);
        }

        return $tong;
    }
}
