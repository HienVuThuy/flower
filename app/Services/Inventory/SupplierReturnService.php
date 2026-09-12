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

/**
 * Trả hàng lại cho nhà cung cấp.
 * ============================================================
 * MỘT KHÁI NIỆM CHO NGƯỜI DÙNG, HAI CƠ CHẾ BÊN DƯỚI — vì hai loại hàng
 * giữ giá vốn ở hai chỗ khác nhau:
 *
 *   - HÀNG ĐẾM ĐƯỢC: giá vốn nằm ở dòng phiếu nhập. Trả hàng = một
 *     phiếu `tra_ncc` với SỐ LƯỢNG ÂM ở đúng đơn giá đã mua. Cùng một
 *     đoạn cộng kho và cùng một bảng giá vốn phục vụ cả hai chiều.
 *
 *   - HOA TƯƠI: giá vốn nằm ở mức LÔ, và hoa không có tồn kho để bớt.
 *     Trả hàng = ghi thẳng lên lô phần đã trả.
 *
 * ============================================================
 * CÁCH XỬ LÝ TIỀN QUYẾT ĐỊNH GIÁ VỐN CÓ GIẢM HAY KHÔNG.
 *
 * Đây là chỗ dễ sai nhất, vì "đã trả hàng rồi thì trừ tiền đi" nghe rất
 * thuận tai:
 *
 *   - Hoàn tiền / trừ công nợ  -> tiền quay về  -> giá vốn GIẢM.
 *   - Đổi hàng khác            -> nhận đủ hàng  -> giá vốn GIỮ NGUYÊN.
 *   - Không được gì            -> cửa hàng chịu -> giá vốn GIỮ NGUYÊN.
 *
 * Trừ giá vốn ở hai trường hợp sau là tự tặng cho cửa hàng một khoản lãi
 * không có thật.
 */
class SupplierReturnService
{
    public function __construct(
        private readonly StockReceiptService $phieu,
        private readonly ActivityLogger $audit,
    ) {
    }

    /**
     * Trả hàng đếm được: lập phiếu `tra_ncc` với số lượng âm.
     *
     * @param  array<int|string, array{quantity?: mixed}>  $dong  khoá theo id dòng phiếu gốc
     *
     * @throws StockReceiptException
     */
    public function traHangDem(StockReceipt $goc, array $dong, array $data): StockReceipt
    {
        $lyDo = ReturnReason::from((string) $data['reason']);
        $cach = ReturnSettlement::from((string) $data['settlement']);

        return DB::transaction(function () use ($goc, $dong, $data, $lyDo, $cach) {
            $khoa = StockReceipt::whereKey($goc->id)->lockForUpdate()->first();

            if (! $khoa) {
                throw new StockReceiptException('Không tìm thấy phiếu nhập gốc.');
            }

            /*
             * CHỈ TRẢ ĐƯỢC HÀNG ĐÃ GHI SỔ.
             *
             * Phiếu còn nháp thì hàng chưa vào kho và chưa vào nền giá
             * vốn — "trả lại" một thứ chưa từng được ghi nhận sẽ đẩy tồn
             * xuống âm và kéo giá vốn đi lệch. Nháp thì sửa phiếu gốc.
             */
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

    /**
     * Trả hàng của một lô hoa.
     *
     * @throws FlowerLotException
     */
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

            /*
             * TIỀN TRẢ LẠI TÍNH THEO ĐƠN GIÁ CỦA CHÍNH LÔ ĐÓ.
             *
             * Không hỏi người dùng gõ tay: gõ tay thì một con số lệch sẽ
             * đi thẳng vào giá vốn hoa của kỳ, và không có gì đối chiếu.
             */
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

    /* ================= BÊN TRONG ================= */

    /**
     * @param  array<int|string, array{quantity?: mixed}>  $dong
     * @return list<array<string, mixed>>
     */
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

                // SỐ ÂM: cùng một đoạn cộng kho và cùng một bảng giá vốn
                // phục vụ cả hai chiều.
                'quantity' => -$sl,

                // ĐÚNG ĐƠN GIÁ ĐÃ MUA. Lấy giá khác là làm lệch giá vốn
                // bình quân của phần hàng còn giữ lại.
                'unit_cost' => $item->unit_cost,
            ];
        }

        return $ket;
    }

    /** Đã trả bao nhiêu cái của một dòng phiếu nhập (đếm trên các phiếu trả). */
    public function soDaTra(int $idDongGoc): int
    {
        // Dòng phiếu trả không trỏ thẳng tới dòng gốc, nên đếm theo mặt
        // hàng trong các phiếu trả của cùng phiếu nhập.
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

    /**
     * Số tiền vựa trả lại.
     *
     * Chỉ có nghĩa khi tiền quay về; đổi hàng hay chịu mất thì để NULL
     * chứ không ghi 0 — 0 là "được trả 0 đồng", một khẳng định khác hẳn.
     *
     * @param  list<array<string, mixed>>  $dongTra
     */
    private function tienTraLai(ReturnSettlement $cach, mixed $goNhap, array $dongTra): ?string
    {
        if (! $cach->tienQuayVe()) {
            return null;
        }

        // Người dùng gõ số thì tin số đó (vựa có thể trả ít hơn); không
        // gõ thì tính theo đúng đơn giá đã mua.
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
