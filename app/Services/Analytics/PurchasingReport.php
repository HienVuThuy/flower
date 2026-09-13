<?php

namespace App\Services\Analytics;

use App\Enums\StockReceiptKind;
use App\Enums\StockReceiptStatus;
use App\Models\FlowerLot;
use App\Models\StockReceiptItem;
use Illuminate\Support\Collection;

/**
 * Phân tích thu mua: lấy hàng ở đâu thì ĐÁNG TIỀN nhất.
 * ============================================================
 * CÂU HỎI KHÔNG PHẢI "CHỖ NÀO RẺ NHẤT".
 *
 * Vựa A bán 50.000 một bó nhưng về tới nơi hỏng 20%. Vựa B bán 55.000 và
 * hỏng 5%. Giá mỗi bó DÙNG ĐƯỢC: A là 62.500, B là 57.895. Vựa "rẻ hơn
 * 5.000" thật ra đắt hơn gần 5.000.
 *
 * Nên báo cáo này in HAI cột giá cạnh nhau, và khi hai cột chỉ về hai
 * vựa khác nhau thì nói thẳng ra. Một mình cột "đơn giá" là con số dẫn
 * người đọc tới quyết định sai mà vẫn thấy mình có số liệu.
 *
 *     Giá dùng được = tiền thật sự tốn / (số lấy về − hao hụt − đã trả)
 *
 * ============================================================
 * KHÔNG BAO GIỜ TRỘN HAI ĐƠN VỊ.
 *
 * Hồng đỏ mua theo bó ở chỗ này, theo cành ở chỗ kia. "Giá trung bình
 * của hồng đỏ" gộp cả hai là một con số không có nghĩa gì — và nó trông
 * y hệt một con số có nghĩa. Nên nhóm là (loại hoa + ĐƠN VỊ), hai đơn vị
 * là hai bảng riêng, không có dòng tổng nào bắc cầu giữa chúng.
 *
 * ============================================================
 * HAI LOẠI HÀNG, HAI NGUỒN SỐ.
 *
 * Hoa tươi đọc ở `flower_lots` — có hao hụt, vì hoa héo là chuyện thường
 * ngày. Hàng đếm được đọc ở phiếu nhập ĐÃ GHI SỔ — không có khái niệm
 * hao hụt, bù lại có tỉ lệ phải trả lại vựa.
 *
 * Phiếu còn nháp KHÔNG tính: hàng chưa vào kho, giá chưa vào nền giá
 * vốn. Đếm nó ở đây thì báo cáo thu mua nói một đằng, giá vốn một nẻo.
 */
class PurchasingReport
{
    /** Dưới ngần này lần mua thì chưa đủ để kết luận về một nguồn. */
    public const DU_LIEU_MONG = 3;

    private KhoangThoiGian $khoang;

    public function __construct()
    {
        $this->khoang = new KhoangThoiGian();
    }

    public function trong(KhoangThoiGian $khoang): static
    {
        $this->khoang = $khoang;

        return $this;
    }

    /**
     * Hoa tươi, nhóm theo (loại hoa + đơn vị).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function hoa(): Collection
    {
        // Nạp sẵn cả hai quan hệ: vòng lặp dưới đọc tên loại hoa và tên
        // vựa của TỪNG lô, và một truy vấn mỗi lô thì trang so giá của
        // một mùa hoa thành vài trăm truy vấn.
        $q = FlowerLot::query()->with(['kind', 'supplier']);

        // Cột DATE: phải lọc theo ngày địa phương, không phải mốc UTC.
        $this->khoang->apDungNgay($q, 'purchased_at');

        $nhom = [];

        foreach ($q->get() as $lo) {
            $khoa = $lo->flower_kind_id . '|' . $lo->unit->value;

            $nhom[$khoa] ??= [
                'ten' => $lo->kind?->name ?? 'Loại hoa đã xoá',
                'don_vi' => $lo->unit->label(),
                'nguon' => [],
            ];

            $ten = $lo->supplier_name ?: $lo->supplier?->name;

            $n = &$nhom[$khoa]['nguon'][$this->khoaNguon($lo->supplier_id, $ten)];

            $n ??= $this->nguonRong($this->tenNguon($ten));

            $n['so_lan']++;
            $n['so_luong'] = bcadd($n['so_luong'], (string) $lo->quantity, 2);
            $n['tien'] = bcadd($n['tien'], (string) $lo->total_cost, 2);
            $n['tien_thuc'] = bcadd($n['tien_thuc'], $lo->tienThucTe(), 2);
            $n['hao'] = bcadd($n['hao'], (string) $lo->hao_hut, 2);
            $n['tra'] = bcadd($n['tra'], (string) ($lo->tra_lai_qty ?? '0'), 2);

            $ngay = $lo->purchased_at->toDateString();

            if ($n['gan_nhat'] === null || $ngay > $n['gan_nhat']) {
                $n['gan_nhat'] = $ngay;
            }

            $thang = substr($ngay, 0, 7);
            $nhom[$khoa]['thang'][$thang] ??= ['so_lan' => 0, 'so_luong' => '0.00', 'tien' => '0.00'];
            $nhom[$khoa]['thang'][$thang]['so_lan']++;
            $nhom[$khoa]['thang'][$thang]['so_luong'] = bcadd($nhom[$khoa]['thang'][$thang]['so_luong'], (string) $lo->quantity, 2);
            $nhom[$khoa]['thang'][$thang]['tien'] = bcadd($nhom[$khoa]['thang'][$thang]['tien'], (string) $lo->total_cost, 2);

            unset($n);
        }

        return $this->dongGoi($nhom, coHaoHut: true);
    }

    /**
     * Hàng đếm được, nhóm theo mặt hàng (kể cả biến thể).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function hang(): Collection
    {
        $q = StockReceiptItem::query()
            ->join('stock_receipts', 'stock_receipts.id', '=', 'stock_receipt_items.stock_receipt_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'stock_receipts.supplier_id')
            ->where('stock_receipts.status', StockReceiptStatus::Posted->value)
            ->whereIn('stock_receipts.kind', [
                StockReceiptKind::NhapMoi->value,
                StockReceiptKind::TraNcc->value,
            ]);

        $this->khoang->apDungNgay($q, 'stock_receipts.received_at');

        $nhom = [];

        $dong = $q->orderBy('stock_receipts.received_at')->get([
            'stock_receipt_items.product_id',
            'stock_receipt_items.product_variant_id',
            'stock_receipt_items.product_name',
            'stock_receipt_items.variant_name',
            'stock_receipt_items.quantity',
            'stock_receipt_items.unit_cost',
            'stock_receipts.kind',
            'stock_receipts.supplier_id',
            'stock_receipts.supplier as supplier_ten',
            'suppliers.name as supplier_bang',
            'stock_receipts.received_at',
        ]);

        foreach ($dong as $d) {
            $khoa = $d->product_id . ':' . ($d->product_variant_id ?? '');
            $laTra = $d->kind === StockReceiptKind::TraNcc->value;

            $nhom[$khoa] ??= [
                'ten' => $d->product_name . ($d->variant_name ? ' — ' . $d->variant_name : ''),
                'don_vi' => 'cái',
                'nguon' => [],
            ];

            $ten = $d->supplier_ten ?: $d->supplier_bang;
            $n = &$nhom[$khoa]['nguon'][$this->khoaNguon($d->supplier_id, $ten)];

            $n ??= $this->nguonRong($this->tenNguon($ten));

            $sl = (int) $d->quantity;
            $ngay = substr((string) $d->received_at, 0, 10);

            if ($laTra) {
                /*
                 * DÒNG TRẢ: số lượng ÂM, và đơn giá của nó là TIỀN LẤY LẠI
                 * ĐƯỢC chứ không phải giá đã mua (xem SupplierReturnService).
                 *
                 * Nên "đã trả" đếm bằng trị tuyệt đối của số lượng, còn
                 * tiền thì CỘNG thẳng dòng âm vào — nó tự trừ đúng phần
                 * vựa đã đền, và tự GIỮ LẠI phần vựa không đền.
                 */
                $n['tra'] = bcadd($n['tra'], (string) abs($sl), 2);
            } else {
                $n['so_lan']++;
                $n['so_luong'] = bcadd($n['so_luong'], (string) $sl, 2);

                if ($n['gan_nhat'] === null || $ngay > $n['gan_nhat']) {
                    $n['gan_nhat'] = $ngay;
                }
            }

            if ($d->unit_cost === null) {
                // Dòng chưa điền giá: đếm riêng, KHÔNG coi là 0 đồng.
                if (! $laTra) {
                    $n['thieu_gia'] += $sl;
                }

                unset($n);

                continue;
            }

            $thanhTien = bcmul((string) $d->unit_cost, (string) $sl, 2);

            if (! $laTra) {
                $n['so_luong_co_gia'] = bcadd($n['so_luong_co_gia'], (string) $sl, 2);
                $n['tien'] = bcadd($n['tien'], $thanhTien, 2);

                $thang = substr($ngay, 0, 7);
                $nhom[$khoa]['thang'][$thang] ??= ['so_lan' => 0, 'so_luong' => '0.00', 'tien' => '0.00'];
                $nhom[$khoa]['thang'][$thang]['so_lan']++;
                $nhom[$khoa]['thang'][$thang]['so_luong'] = bcadd($nhom[$khoa]['thang'][$thang]['so_luong'], (string) $sl, 2);
                $nhom[$khoa]['thang'][$thang]['tien'] = bcadd($nhom[$khoa]['thang'][$thang]['tien'], $thanhTien, 2);
            }

            // Dòng âm cộng vào đây là tự trừ phần đã được đền.
            $n['tien_thuc'] = bcadd($n['tien_thuc'], $thanhTien, 2);

            unset($n);
        }

        return $this->dongGoi($nhom, coHaoHut: false);
    }

    /**
     * Tiền lấy hàng trong kỳ — cho trang Tổng quan.
     *
     * CÙNG BỘ LỌC với hoa() và hang(): phiếu nhập MỚI đã ghi sổ, lô hoa theo
     * ngày lấy. Hai nơi hai định nghĩa thì con số ở Tổng quan và ở trang
     * Thu mua lệch nhau. Dòng chưa điền giá không cộng — nó không phải 0đ.
     *
     * @return array{tien_hang: string, so_phieu: int, tien_hoa: string, so_lo: int, tong: string}
     */
    public function tongQuan(): array
    {
        $dong = StockReceiptItem::query()
            ->join('stock_receipts', 'stock_receipts.id', '=', 'stock_receipt_items.stock_receipt_id')
            ->where('stock_receipts.status', StockReceiptStatus::Posted->value)
            ->where('stock_receipts.kind', StockReceiptKind::NhapMoi->value)
            ->whereNotNull('stock_receipt_items.unit_cost');
        $this->khoang->apDungNgay($dong, 'stock_receipts.received_at');

        $tienHang = '0.00';
        $phieu = [];

        foreach ($dong->get(['stock_receipt_items.unit_cost', 'stock_receipt_items.quantity', 'stock_receipts.id as phieu_id']) as $d) {
            $tienHang = bcadd($tienHang, bcmul((string) $d->unit_cost, (string) $d->quantity, 2), 2);
            $phieu[$d->phieu_id] = true;
        }

        $lo = FlowerLot::query();
        $this->khoang->apDungNgay($lo, 'purchased_at');

        $tienHoa = '0.00';
        $soLo = 0;

        foreach ($lo->get(['total_cost']) as $l) {
            $tienHoa = bcadd($tienHoa, (string) $l->total_cost, 2);
            $soLo++;
        }

        return [
            'tien_hang' => $tienHang,
            'so_phieu' => count($phieu),
            'tien_hoa' => $tienHoa,
            'so_lo' => $soLo,
            'tong' => bcadd($tienHang, $tienHoa, 2),
        ];
    }

    /**
     * Những lần mua KHÔNG GHI NGUỒN — không so sánh được với gì cả.
     *
     * Hiện ra chứ không lặng lẽ bỏ: một báo cáo so giá mà một phần ba số
     * lần mua rơi vào "không rõ ở đâu" thì kết luận của nó cũng chỉ đúng
     * hai phần ba, và người đọc cần biết điều đó.
     *
     * @return array{lo_hoa: int, phieu: int}
     */
    public function thieuNguon(): array
    {
        $lo = FlowerLot::query()->whereNull('supplier_id')->whereNull('supplier_name');
        $this->khoang->apDungNgay($lo, 'purchased_at');

        $phieu = \App\Models\StockReceipt::query()
            ->where('status', StockReceiptStatus::Posted->value)
            ->where('kind', StockReceiptKind::NhapMoi->value)
            ->whereNull('supplier_id')
            ->where(fn ($q) => $q->whereNull('supplier')->orWhere('supplier', ''));
        $this->khoang->apDungNgay($phieu, 'received_at');

        return [
            'lo_hoa' => $lo->count(),
            'phieu' => $phieu->count(),
        ];
    }

    /* ================= BÊN TRONG ================= */

    /**
     * Khoá gom nhóm cho một nguồn hàng.
     *
     * Ưu tiên id: cùng một vựa được chọn từ danh sách thì luôn về một
     * nhóm dù tên bản chụp trên phiếu có khác. Chỉ khi không có id mới
     * gom theo tên đã gõ tay — và tên gõ tay thì chuẩn hoá khoảng trắng
     * với chữ hoa chữ thường, chứ "Vựa Bình" và "vựa bình " tách thành
     * hai nguồn là chia đôi số liệu của cùng một chỗ.
     */
    private function khoaNguon(?int $id, ?string $ten): string
    {
        if ($id !== null) {
            return 'id:' . $id;
        }

        $ten = trim((string) $ten);

        return $ten === ''
            ? 'khong-ro'
            : 'ten:' . mb_strtolower(preg_replace('/\s+/u', ' ', $ten));
    }

    /** Tên để hiển thị, hoặc null khi lần mua đó không ghi nguồn. */
    private function tenNguon(?string $ten): ?string
    {
        return trim((string) $ten) ?: null;
    }

    /** @return array<string, mixed> */
    private function nguonRong(?string $ten): array
    {
        return [
            'ten' => $ten,
            'so_lan' => 0,
            'so_luong' => '0.00',
            'so_luong_co_gia' => '0.00',
            'tien' => '0.00',
            'tien_thuc' => '0.00',
            'hao' => '0.00',
            'tra' => '0.00',
            'thieu_gia' => 0,
            'gan_nhat' => null,
        ];
    }

    /**
     * Tính các con số dẫn xuất và xếp thứ tự.
     *
     * @param  array<string, array<string, mixed>>  $nhom
     * @return Collection<int, array<string, mixed>>
     */
    private function dongGoi(array $nhom, bool $coHaoHut): Collection
    {
        $ket = [];

        foreach ($nhom as $g) {
            $nguon = [];

            foreach ($g['nguon'] as $n) {
                $nguon[] = $this->tinhNguon($n, $coHaoHut);
            }

            /*
             * XẾP THEO GIÁ DÙNG ĐƯỢC, không theo đơn giá.
             *
             * Đây là cả luận điểm của trang: dòng đầu bảng phải là chỗ
             * đáng tiền nhất. Nguồn chưa tính được giá dùng được thì
             * xuống cuối — không có số thì không đứng đầu bảng được.
             */
            usort($nguon, function ($a, $b) {
                if ($a['gia_dung_duoc'] === null || $b['gia_dung_duoc'] === null) {
                    return ($a['gia_dung_duoc'] === null ? 1 : 0) <=> ($b['gia_dung_duoc'] === null ? 1 : 0);
                }

                return bccomp($a['gia_dung_duoc'], $b['gia_dung_duoc'], 2);
            });

            $reNhat = $this->tenTotNhat($nguon, 'don_gia');
            $dangTien = $this->tenTotNhat($nguon, 'gia_dung_duoc');

            $thang = $g['thang'] ?? [];
            ksort($thang);

            $ket[] = [
                'ten' => $g['ten'],
                'don_vi' => $g['don_vi'],
                'nguon' => $nguon,
                'so_nguon' => count($nguon),
                'so_lan' => array_sum(array_column($nguon, 'so_lan')),
                're_nhat' => $reNhat,
                'dang_tien_nhat' => $dangTien,

                /*
                 * CÓ ĐÁNG NÓI KHÔNG: chỉ khi có từ hai nguồn trở lên VÀ
                 * hai câu trả lời khác nhau. Một nguồn thì "rẻ nhất" và
                 * "đáng tiền nhất" luôn trùng, in ra là nói một câu rỗng.
                 */
                'khac_nhau' => count($nguon) > 1
                    && $reNhat !== null
                    && $dangTien !== null
                    && $reNhat !== $dangTien,

                'thang' => $this->theoThang($thang),
            ];
        }

        // Nhiều lần mua nhất lên trước: đó là chỗ tiền của cửa hàng đi qua.
        usort($ket, fn ($a, $b) => $b['so_lan'] <=> $a['so_lan']);

        return collect($ket);
    }

    /**
     * @param  array<string, mixed>  $n
     * @return array<string, mixed>
     */
    private function tinhNguon(array $n, bool $coHaoHut): array
    {
        // Hàng đếm được: chỉ phần có điền giá mới chia ra đơn giá được.
        $mau = $coHaoHut ? $n['so_luong'] : $n['so_luong_co_gia'];

        $n['co_hao_hut'] = $coHaoHut;
        $n['don_gia'] = bccomp($mau, '0', 2) > 0 ? bcdiv($n['tien'], $mau, 2) : null;

        $n['ti_le_hao'] = $coHaoHut && bccomp($n['so_luong'], '0', 2) > 0
            ? round((float) $n['hao'] / (float) $n['so_luong'] * 100, 1)
            : null;

        $n['ti_le_tra'] = bccomp($n['so_luong'], '0', 2) > 0
            ? round((float) $n['tra'] / (float) $n['so_luong'] * 100, 1)
            : null;

        /*
         * GIÁ MỖI ĐƠN VỊ THẬT SỰ DÙNG ĐƯỢC.
         *
         * Tử số là tiền THẬT SỰ tốn — đã trừ phần vựa đền, và chỉ phần
         * vựa đền. Mẫu số là số còn dùng để bán: trừ cả hao hụt lẫn phần
         * đã trả lại.
         *
         * Mẫu số ≤ 0 thì trả null chứ không trả 0 hay một con số to: "giá
         * mỗi bó dùng được của một lần mua không còn bó nào dùng được"
         * không phải một câu có nghĩa.
         */
        $dungDuoc = bcsub(bcsub($mau, $coHaoHut ? $n['hao'] : '0.00', 2), $n['tra'], 2);

        $n['dung_duoc'] = $dungDuoc;
        $n['gia_dung_duoc'] = bccomp($dungDuoc, '0', 2) > 0 && bccomp($n['tien_thuc'], '0', 2) > 0
            ? bcdiv($n['tien_thuc'], $dungDuoc, 2)
            : null;

        // Ít lần mua quá thì con số vẫn đúng, chỉ là chưa nói lên điều gì.
        $n['mong'] = $n['so_lan'] < self::DU_LIEU_MONG;

        return $n;
    }

    /**
     * Tên nguồn có chỉ số nhỏ nhất, hoặc null khi hoà / không đủ số.
     *
     * HOÀ THÌ KHÔNG CÓ QUÁN QUÂN: hai vựa cùng giá mà tô đậm một cái là
     * dựng ra một sự khác biệt không có thật.
     *
     * @param  list<array<string, mixed>>  $nguon
     */
    private function tenTotNhat(array $nguon, string $cot): ?string
    {
        $co = array_values(array_filter($nguon, fn ($n) => $n[$cot] !== null));

        if ($co === []) {
            return null;
        }

        usort($co, fn ($a, $b) => bccomp($a[$cot], $b[$cot], 2));

        if (count($co) > 1 && bccomp($co[0][$cot], $co[1][$cot], 2) === 0) {
            return null;
        }

        return $co[0]['ten'] ?? 'Không ghi nguồn';
    }

    /**
     * Giá bình quân theo tháng — để thấy giá đang lên hay xuống.
     *
     * @param  array<string, array{so_lan: int, so_luong: string, tien: string}>  $thang
     * @return list<array<string, mixed>>
     */
    private function theoThang(array $thang): array
    {
        $ket = [];
        $truoc = null;

        foreach ($thang as $ma => $t) {
            $gia = bccomp($t['so_luong'], '0', 2) > 0 ? bcdiv($t['tien'], $t['so_luong'], 2) : null;

            $ket[] = [
                'thang' => $ma,
                'nhan' => substr($ma, 5, 2) . '/' . substr($ma, 0, 4),
                'so_lan' => $t['so_lan'],
                'gia' => $gia,

                /*
                 * SO VỚI THÁNG LIỀN TRƯỚC CÓ SỐ LIỆU, không phải với
                 * tháng đầu kỳ: người đọc muốn biết "lần này so lần
                 * trước", và tháng không mua gì thì không có mốc để so.
                 */
                'doi' => $gia !== null && $truoc !== null && bccomp($truoc, '0', 2) > 0
                    ? round((((float) $gia - (float) $truoc) / (float) $truoc) * 100, 1)
                    : null,
            ];

            if ($gia !== null) {
                $truoc = $gia;
            }
        }

        return $ket;
    }
}
