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

            // Đơn giá trên dòng trả phải là TIỀN LẤY LẠI ĐƯỢC, không phải
            // giá đã mua — xem chú thích ở chiaTienVeCacDong().
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

                // GIÁ ĐÃ MUA — mới chỉ là nền để chia tiền. Giá thật sự
                // ghi lên dòng do chiaTienVeCacDong() đặt.
                'unit_cost' => $item->unit_cost,
            ];
        }

        return $ket;
    }

    /**
     * Đặt đơn giá cho các dòng phiếu trả = TIỀN THẬT SỰ LẤY LẠI ĐƯỢC.
     * ============================================================
     * VÌ SAO KHÔNG PHẢI GIÁ ĐÃ MUA — dù nghe rất thuận tai.
     *
     * Nền giá vốn là trung bình động: cộng dồn `tiền` và `số lượng` qua
     * mọi dòng phiếu, rồi chia. Một dòng âm ở ĐÚNG giá đã mua rút cả
     * tiền lẫn hàng theo đúng tỉ lệ, nên giá mỗi cái còn lại KHÔNG ĐỔI.
     *
     * Đúng khi vựa đền đủ. Sai hẳn khi vựa không đền:
     *
     *   Mua 10 @100.000 = 1.000.000. Hỏng 3, vựa không đền gì.
     *   - Ghi dòng trả @100.000 -> nền 700.000 / 7 = 100.000/cái.
     *   - Sự thật: đã tiêu 1.000.000, còn 7 cái = 142.857/cái.
     *
     * Ba cái hỏng bốc hơi khỏi sổ sách. Lỗ 300.000 biến mất, lãi gộp cao
     * hơn sự thật, và không có gì báo — đúng hướng sai mà không ai tự đi
     * kiểm. (Bài kiểm thử tra_ma_KHONG_duoc_gi... giữ chỗ này.)
     *
     * Đặt đơn giá theo tiền lấy lại được thì cả bốn cách xử lý ra đúng
     * bằng một quy tắc, không cần trường hợp riêng:
     *
     *   - Hoàn đủ      -> đúng bằng giá mua -> giá mỗi cái giữ nguyên.
     *   - Hoàn thiếu   -> thấp hơn giá mua  -> phần hụt ở lại giá vốn.
     *   - Đổi hàng     -> 0đ                -> tiền ở lại, chờ phiếu nhập
     *                                         0đ khi hàng đổi về.
     *   - Không được gì-> 0đ                -> cửa hàng chịu, và sổ nói ra.
     *
     * ============================================================
     * CHIA KHÔNG HẾT THÌ LÀM TRÒN XUỐNG.
     *
     * `bcdiv` cắt phần lẻ, nên đơn giá trả lại luôn nhỏ hơn hoặc bằng
     * phần đáng được chia: 200.000 cho 3 cái ra 66.666,66 chứ không phải
     * 66.666,67. Phần lẻ ở lại trong giá vốn — tức nghiêng về phía giá
     * vốn CAO hơn một chút chứ không thấp hơn. Sai số vài xu, và nghiêng
     * về phía thận trọng là có chủ đích: làm tròn kiểu kia thì lãi đẹp
     * lên, và đó là hướng không ai tự đi kiểm.
     *
     * @param  list<array<string, mixed>>  $dongTra
     * @return list<array<string, mixed>>
     */
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
            /*
             * DÒNG CHƯA ĐIỀN GIÁ VẪN ĐỂ TRỐNG, không hạ thành 0.
             *
             * NULL là "không biết mua bao nhiêu"; 0 là "lấy lại được 0
             * đồng". Bảng giá vốn bỏ qua NULL và đếm 0 — đổi cái này
             * thành cái kia là tự bịa ra một con số chưa ai nhập.
             */
            if ($d['unit_cost'] === null) {
                continue;
            }

            if ($tien === null || bccomp($nen, '0', 2) <= 0) {
                $dongTra[$i]['unit_cost'] = '0.00';

                continue;
            }

            $sl = (string) abs((int) $d['quantity']);

            // Chia theo GIÁ TRỊ của dòng, không theo số lượng: trả 1 cái
            // đắt và 1 cái rẻ thì tiền đền không chia đôi.
            $phan = bcdiv(bcmul($tien, bcmul((string) $d['unit_cost'], $sl, 2), 4), $nen, 4);

            $dongTra[$i]['unit_cost'] = bcdiv($phan, $sl, 2);
        }

        return $dongTra;
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
