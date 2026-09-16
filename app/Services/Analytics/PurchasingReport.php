<?php

namespace App\Services\Analytics;

use App\Enums\StockReceiptKind;
use App\Enums\StockReceiptStatus;
use App\Models\FlowerLot;
use App\Models\StockReceiptItem;
use Illuminate\Support\Collection;

/** Phân tích thu mua: lấy hàng ở đâu thì ĐÁNG TIỀN nhất. */
class PurchasingReport
{
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

    public function hoa(): Collection
    {
        $q = FlowerLot::query()->with(['kind', 'supplier']);

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
                $n['tra'] = bcadd($n['tra'], (string) abs($sl), 2);
            } else {
                $n['so_lan']++;
                $n['so_luong'] = bcadd($n['so_luong'], (string) $sl, 2);

                if ($n['gan_nhat'] === null || $ngay > $n['gan_nhat']) {
                    $n['gan_nhat'] = $ngay;
                }
            }

            if ($d->unit_cost === null) {
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

            $n['tien_thuc'] = bcadd($n['tien_thuc'], $thanhTien, 2);

            unset($n);
        }

        return $this->dongGoi($nhom, coHaoHut: false);
    }

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

    private function tenNguon(?string $ten): ?string
    {
        return trim((string) $ten) ?: null;
    }

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

    private function dongGoi(array $nhom, bool $coHaoHut): Collection
    {
        $ket = [];

        foreach ($nhom as $g) {
            $nguon = [];

            foreach ($g['nguon'] as $n) {
                $nguon[] = $this->tinhNguon($n, $coHaoHut);
            }

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

                'khac_nhau' => count($nguon) > 1
                    && $reNhat !== null
                    && $dangTien !== null
                    && $reNhat !== $dangTien,

                'thang' => $this->theoThang($thang),
            ];
        }

        usort($ket, fn ($a, $b) => $b['so_lan'] <=> $a['so_lan']);

        return collect($ket);
    }

    private function tinhNguon(array $n, bool $coHaoHut): array
    {
        $mau = $coHaoHut ? $n['so_luong'] : $n['so_luong_co_gia'];

        $n['co_hao_hut'] = $coHaoHut;
        $n['don_gia'] = bccomp($mau, '0', 2) > 0 ? bcdiv($n['tien'], $mau, 2) : null;

        $n['ti_le_hao'] = $coHaoHut && bccomp($n['so_luong'], '0', 2) > 0
            ? round((float) $n['hao'] / (float) $n['so_luong'] * 100, 1)
            : null;

        $n['ti_le_tra'] = bccomp($n['so_luong'], '0', 2) > 0
            ? round((float) $n['tra'] / (float) $n['so_luong'] * 100, 1)
            : null;

        $dungDuoc = bcsub(bcsub($mau, $coHaoHut ? $n['hao'] : '0.00', 2), $n['tra'], 2);

        $n['dung_duoc'] = $dungDuoc;
        $n['gia_dung_duoc'] = bccomp($dungDuoc, '0', 2) > 0 && bccomp($n['tien_thuc'], '0', 2) > 0
            ? bcdiv($n['tien_thuc'], $dungDuoc, 2)
            : null;

        $n['mong'] = $n['so_lan'] < self::DU_LIEU_MONG;

        return $n;
    }

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
