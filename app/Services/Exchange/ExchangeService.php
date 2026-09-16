<?php

namespace App\Services\Exchange;

use App\Enums\ExchangeReason;
use App\Enums\ExchangeStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Enums\RefundMethod;
use App\Enums\RefundReason;
use App\Models\Exchange;
use App\Models\ExchangeItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\StockReturn;
use App\Services\Refund\RefundService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Đổi hàng: khách trả món này, nhận món khác. */
class ExchangeService
{
    public const HAN_DOI_NGAY = 7;

    public function __construct(
        private readonly StockReturn $stock,
        private readonly RefundService $refunds,
    ) {
    }

    public function lyDoKhongDoiDuoc(Order $order): ?string
    {
        if ($order->status !== OrderStatus::Completed) {
            return 'Chỉ đổi hàng cho đơn đã giao. Đơn chưa giao thì sửa đơn hoặc huỷ đơn.';
        }

        if ($order->completed_at === null) {
            return 'Đơn này chưa có mốc giao hàng nên không tính được hạn đổi.';
        }

        $hetHan = $order->completed_at->copy()->addDays(self::HAN_DOI_NGAY);

        if ($hetHan->isPast()) {
            return sprintf(
                'Đã quá hạn đổi %d ngày kể từ khi giao (hết hạn %s).',
                self::HAN_DOI_NGAY,
                \App\Services\Time\Gio::hien($hetHan)->format('d/m/Y'),
            );
        }

        if ($this->dongDoiDuoc($order)->isEmpty()) {
            return 'Đơn này không có món nào còn đổi được.';
        }

        return null;
    }

    public function lyDoDongKhongDoiDuoc(OrderItem $item): ?string
    {
        if ($item->product?->product_type === ProductType::Flower) {
            return 'Hoa tươi không đổi được — hàng quay về không bán lại cho ai được.';
        }

        if (! app(\App\Services\Gift\GiftReturnCalculator::class)->choDoi($item)) {
            return 'Quà tặng miễn phí không đổi được sang hàng khác.';
        }

        if ($this->conDoiDuoc($item) <= 0) {
            return 'Món này đã đổi hoặc đã trả về hết.';
        }

        return null;
    }

    public function dongDoiDuoc(Order $order): \Illuminate\Support\Collection
    {
        return $order->items
            ->loadMissing('product')
            ->filter(fn (OrderItem $i) => $this->lyDoDongKhongDoiDuoc($i) === null)
            ->values();
    }

    public function soDaDoi(OrderItem $item): int
    {
        return (int) ExchangeItem::query()
            ->where('chieu', ExchangeItem::TRA_VE)
            ->where('order_item_id', $item->id)
            ->whereHas('exchange', fn ($q) => $q->where('status', '!=', ExchangeStatus::Huy->value))
            ->sum('quantity');
    }

    public function conDoiDuoc(OrderItem $item): int
    {
        return max(0, (int) $item->quantity - $this->soDaDoi($item) - $this->refunds->soDaTra($item));
    }

    public function tao(Order $order, array $data): Exchange
    {
        $lyDo = ExchangeReason::from((string) $data['reason']);

        return DB::transaction(function () use ($order, $data, $lyDo) {
            $khoa = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $khoa) {
                throw new ExchangeException('Không tìm thấy đơn hàng.');
            }

            $khoa->load('items.product');

            if ($ly = $this->lyDoKhongDoiDuoc($khoa)) {
                throw new ExchangeException($ly);
            }

            $dongTra = $this->dongHangTra($khoa, $data['tra'] ?? []);

            if ($dongTra === []) {
                throw new ExchangeException('Phải chọn ít nhất một món khách trả về.');
            }

            $dongMoi = $this->dongHangMoi($data['moi'] ?? []);

            if ($dongMoi === []) {
                throw new ExchangeException('Phải chọn ít nhất một món gửi cho khách.');
            }

            $tienTra = $this->tong($dongTra);
            $tienMoi = $this->tong($dongMoi);

            $phiShip = $lyDo->cuaHangChiuPhiShip()
                ? '0.00'
                : bcadd((string) $khoa->shipping_fee, '0', 2);

            $chenh = bcsub(bcadd($tienMoi, $phiShip, 2), $tienTra, 2);

            $phieu = new Exchange();

            $phieu->forceFill([
                'order_id' => $khoa->id,
                'reason' => $lyDo,
                'note' => trim((string) ($data['note'] ?? '')) ?: null,
                'code' => $this->sinhMa(),
                'tien_hang_tra' => $tienTra,
                'tien_hang_moi' => $tienMoi,
                'phi_ship' => $phiShip,
                'chenh_lech' => $chenh,
                'status' => ExchangeStatus::ChoNhan,
                'created_by' => Auth::id(),
            ])->save();

            foreach ([...$dongTra, ...$dongMoi] as $dong) {
                $phieu->items()->create($dong);
            }

            foreach ($dongMoi as $dong) {
                $this->giuHang($dong, -1);
            }

            if (bccomp($chenh, '0', 2) < 0) {
                try {
                    $refund = $this->refunds->hoan($khoa, [
                        'amount' => (int) abs((float) $chenh),
                        'reason' => RefundReason::Other->value,
                        'method' => RefundMethod::Cash->value,
                        'note' => 'Chênh lệch đổi hàng ' . $phieu->code,
                        'items' => [],
                    ]);
                } catch (\App\Services\Refund\RefundException $e) {
                    throw new ExchangeException(
                        'Không lập được khoản trả lại chênh lệch: ' . $e->getMessage()
                    );
                }

                $phieu->forceFill(['refund_id' => $refund->id])->save();
            }

            return $phieu->fresh(['items', 'refund']);
        });
    }

    public function daNhanHang(Exchange $phieu, array $banLaiDuoc = []): void
    {
        DB::transaction(function () use ($phieu, $banLaiDuoc) {
            $khoa = Exchange::whereKey($phieu->id)->lockForUpdate()->first();

            if (! $khoa || $khoa->status !== ExchangeStatus::ChoNhan) {
                throw new ExchangeException('Phiếu này không còn ở bước chờ nhận hàng.');
            }

            $khoa->load('hangTra.orderItem');

            foreach ($khoa->hangTra as $dong) {
                $con = filter_var($banLaiDuoc[$dong->id] ?? false, FILTER_VALIDATE_BOOL);

                $dong->forceFill(['restock' => $con])->save();

                if ($con && $dong->orderItem) {
                    $this->stock->congLai($dong->orderItem, $dong->quantity);
                }
            }

            $khoa->forceFill([
                'status' => ExchangeStatus::DaNhan,
                'nhan_hang_at' => now(),
            ])->save();
        });

        $phieu->refresh();
    }

    public function hoanTat(Exchange $phieu, string|int $daThu = 0): void
    {
        DB::transaction(function () use ($phieu, $daThu) {
            $khoa = Exchange::whereKey($phieu->id)->lockForUpdate()->first();

            if (! $khoa || $khoa->status !== ExchangeStatus::DaNhan) {
                throw new ExchangeException('Phải nhận hàng cũ về trước khi hoàn tất phiếu.');
            }

            $so = bcadd((string) (int) $daThu, '0', 2);

            if (bccomp($so, '0', 2) < 0) {
                throw new ExchangeException('Số tiền đã thu không thể là số âm.');
            }

            if (bccomp($so, $khoa->conPhaiThu(), 2) > 0) {
                throw new ExchangeException(sprintf(
                    'Khách chỉ còn phải bù %s.',
                    \App\Services\Shop\Money::format($khoa->conPhaiThu()),
                ));
            }

            $khoa->forceFill([
                'da_thu' => bcadd((string) $khoa->da_thu, $so, 2),
                'status' => ExchangeStatus::HoanTat,
                'hoan_tat_at' => now(),
            ])->save();
        });

        $phieu->refresh();
    }

    public function huy(Exchange $phieu, string $lyDo): void
    {
        $lyDo = trim($lyDo);

        if ($lyDo === '') {
            throw new ExchangeException('Huỷ phiếu phải ghi lý do — người đọc sau này cần biết vì sao.');
        }

        DB::transaction(function () use ($phieu, $lyDo) {
            $khoa = Exchange::whereKey($phieu->id)->lockForUpdate()->first();

            if (! $khoa || $khoa->status->daXong()) {
                throw new ExchangeException('Phiếu đã hoàn tất hoặc đã huỷ thì không huỷ lại được.');
            }

            $khoa->load('hangMoi', 'hangTra.orderItem');

            foreach ($khoa->hangMoi as $dong) {
                $this->giuHang([
                    'product_id' => $dong->product_id,
                    'product_variant_id' => $dong->product_variant_id,
                    'quantity' => $dong->quantity,
                    'ten_hang' => $dong->ten_hang,
                ], 1);
            }

            if ($khoa->status === ExchangeStatus::DaNhan) {
                foreach ($khoa->hangTra as $dong) {
                    if ($dong->restock && $dong->orderItem) {
                        $this->giuHang([
                            'product_id' => $dong->orderItem->product_id,
                            'product_variant_id' => $dong->orderItem->product_variant_id,
                            'quantity' => $dong->quantity,
                            'ten_hang' => $dong->ten_hang,
                        ], -1);
                    }
                }
            }

            $khoa->forceFill([
                'status' => ExchangeStatus::Huy,
                'huy_at' => now(),
                'ly_do_huy' => Str::limit($lyDo, 250, ''),
            ])->save();
        });

        $phieu->refresh();
    }

    private function dongHangTra(Order $khoa, array $items): array
    {
        $ket = [];

        foreach ($items as $idDong => $dong) {
            $soLuong = (int) ($dong['quantity'] ?? 0);

            if ($soLuong <= 0) {
                continue;
            }

            $item = $khoa->items->firstWhere('id', (int) $idDong);

            if (! $item) {
                throw new ExchangeException('Có dòng hàng không thuộc đơn này.');
            }

            if ($ly = $this->lyDoDongKhongDoiDuoc($item)) {
                throw new ExchangeException('"' . $item->product_name . '": ' . $ly);
            }

            $con = $this->conDoiDuoc($item);

            if ($soLuong > $con) {
                throw new ExchangeException(sprintf(
                    '"%s" chỉ còn đổi được %d.',
                    $item->product_name,
                    $con,
                ));
            }

            $ket[] = [
                'chieu' => ExchangeItem::TRA_VE,
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'ten_hang' => $item->product_name . ($item->variant_name ? ' — ' . $item->variant_name : ''),
                'quantity' => $soLuong,
                'unit_price' => $item->unit_price,
                'restock' => false,
            ];
        }

        return $ket;
    }

    private function dongHangMoi(array $items): array
    {
        $ket = [];

        foreach ($items as $dong) {
            $soLuong = (int) ($dong['quantity'] ?? 0);
            $idSp = (int) ($dong['product_id'] ?? 0);

            if ($soLuong <= 0 || $idSp <= 0) {
                continue;
            }

            $sp = Product::find($idSp);

            if (! $sp) {
                throw new ExchangeException('Có món gửi đi không tồn tại.');
            }

            if ($sp->product_type === ProductType::Flower) {
                throw new ExchangeException(
                    '"' . $sp->name . '" là hoa tươi — không dùng làm hàng đổi được.'
                );
            }

            $idQc = (int) ($dong['variant_id'] ?? 0) ?: null;
            $qc = $idQc ? ProductVariant::where('product_id', $sp->id)->find($idQc) : null;

            if ($idQc && ! $qc) {
                throw new ExchangeException('Quy cách không thuộc sản phẩm đã chọn.');
            }

            $gia = $qc?->price ?? $sp->price()->finalPrice;

            if ($gia === null) {
                throw new ExchangeException(
                    '"' . $sp->name . '" chưa có giá nên không tính được chênh lệch.'
                );
            }

            $ket[] = [
                'chieu' => ExchangeItem::GUI_DI,
                'order_item_id' => null,
                'product_id' => $sp->id,
                'product_variant_id' => $qc?->id,
                'ten_hang' => $sp->name . ($qc ? ' — ' . $qc->name : ''),
                'quantity' => $soLuong,
                'unit_price' => bcadd((string) $gia, '0', 2),
                'restock' => false,
            ];
        }

        return $ket;
    }

    private function tong(array $dong): string
    {
        $tong = '0.00';

        foreach ($dong as $d) {
            $tong = bcadd($tong, bcmul((string) $d['unit_price'], (string) $d['quantity'], 2), 2);
        }

        return $tong;
    }

    private function giuHang(array $dong, int $huong): void
    {
        $so = (int) $dong['quantity'] * $huong;

        if ($so === 0) {
            return;
        }

        if ($dong['product_variant_id']) {
            ProductVariant::whereKey($dong['product_variant_id'])
                ->where('track_inventory', true)
                ->increment('stock_quantity', $so);

            return;
        }

        if ($dong['product_id']) {
            Product::whereKey($dong['product_id'])
                ->where('track_inventory', true)
                ->increment('stock_quantity', $so);
        }
    }

    private function sinhMa(): string
    {
        for ($lan = 0; $lan < 5; $lan++) {
            $ma = sprintf('DH-%s-%s', now()->format('ymd'), Str::upper(Str::random(4)));

            if (! Exchange::where('code', $ma)->exists()) {
                return $ma;
            }
        }

        throw new ExchangeException('Không sinh được mã phiếu đổi, vui lòng thử lại.');
    }
}
