<?php

namespace App\Services\Refund;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\RefundMethod;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Services\Audit\ActivityLogger;
use App\Services\Inventory\StockReturn;
use App\Services\Order\OrderMailer;
use App\Services\Payment\MomoGateway;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** NƠI DUY NHẤT trả tiền lại cho khách. */
class RefundService
{
    public const MOMO_TOI_THIEU = 1000;

    public function __construct(
        private readonly MomoGateway $momo,
        private readonly StockReturn $stock,
        private readonly ActivityLogger $audit,
        private readonly OrderMailer $mailer,
    ) {
    }

    public function lyDoKhongHoanDuoc(Order $order): ?string
    {
        if (! in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Completed], true)) {
            return 'Chỉ hoàn tiền cho đơn đã huỷ hoặc đã giao. Đơn đang xử lý thì huỷ đơn trước.';
        }

        if ($order->payment_status === PaymentStatus::Refunded) {
            return 'Đơn này đã được hoàn đủ tiền.';
        }

        if ($order->payment_status !== PaymentStatus::Paid && bccomp($order->daThu(), '0', 2) <= 0) {
            return 'Khách chưa trả tiền cho đơn này nên không có gì để hoàn.';
        }

        if (bccomp($order->refundableAmount(), '0', 2) <= 0) {
            return 'Toàn bộ số tiền của đơn đã được hoàn, hoặc đang chờ kết quả hoàn.';
        }

        return null;
    }

    public function giaoDichMomo(Order $order): ?PaymentTransaction
    {
        return $order->transactions()
            ->where('gateway', MomoGateway::GATEWAY)
            ->where('status', PaymentTransactionStatus::Paid->value)
            ->whereNotNull('transaction_id')
            ->latest('id')
            ->first();
    }

    public function hoanQuaMomoDuoc(Order $order): bool
    {
        if (! $this->momo->configured()) {
            return false;
        }

        return $order->transactions()
            ->where('gateway', MomoGateway::GATEWAY)
            ->where('status', PaymentTransactionStatus::Paid->value)
            ->whereNotNull('transaction_id')
            ->count() === 1;
    }

    public function cachHoan(Order $order): array
    {
        $ds = [];

        if ($this->hoanQuaMomoDuoc($order)) {
            $ds[] = RefundMethod::Momo;
        }

        $ds[] = RefundMethod::BankTransfer;
        $ds[] = RefundMethod::Cash;

        return $ds;
    }

    public function soDaTra(OrderItem $item): int
    {
        return (int) RefundItem::query()
            ->where('order_item_id', $item->id)
            ->whereHas('refund', fn ($q) => $q->where('status', '!=', RefundStatus::Failed->value))
            ->sum('quantity');
    }

    public function hoan(Order $order, array $data): Refund
    {
        $cach = RefundMethod::from((string) $data['method']);
        $lyDo = RefundReason::from((string) $data['reason']);

        $soTien = bcadd((string) (int) $data['amount'], '0', 2);
        $maGiaoDich = trim((string) ($data['reference'] ?? '')) ?: null;

        $refund = DB::transaction(function () use ($order, $data, $cach, $lyDo, $soTien, $maGiaoDich) {
            $khoa = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $khoa) {
                throw new RefundException('Không tìm thấy đơn hàng.');
            }

            $khoa->load(['refunds', 'items']);

            if ($lyDoKhong = $this->lyDoKhongHoanDuoc($khoa)) {
                throw new RefundException($lyDoKhong);
            }

            if (! in_array($lyDo, RefundReason::choTrangThai($khoa->status), true)) {
                throw new RefundException(sprintf(
                    'Lý do "%s" không hợp với đơn đang ở trạng thái "%s".',
                    $lyDo->label(),
                    $khoa->status->label(),
                ));
            }

            if (bccomp($soTien, '0', 2) <= 0) {
                throw new RefundException('Số tiền hoàn phải lớn hơn 0.');
            }

            if (bccomp($soTien, $khoa->refundableAmount(), 2) > 0) {
                throw new RefundException(sprintf(
                    'Chỉ còn hoàn được %s cho đơn này.',
                    \App\Services\Shop\Money::format($khoa->refundableAmount()),
                ));
            }

            if ($cach === RefundMethod::Momo) {
                if (! $this->hoanQuaMomoDuoc($khoa)) {
                    throw new RefundException('Đơn này không có đúng một giao dịch MoMo thành công nên không hoàn qua MoMo được. Hãy hoàn bằng chuyển khoản hoặc tiền mặt.');
                }

                if ((int) $soTien < self::MOMO_TOI_THIEU) {
                    throw new RefundException('MoMo chỉ nhận hoàn từ 1.000₫ trở lên.');
                }
            }

            if ($cach->batBuocMaGiaoDich() && $maGiaoDich === null) {
                throw new RefundException('Chuyển khoản phải có mã giao dịch ngân hàng, để còn tra khi khách báo chưa nhận được.');
            }

            $dongTra = $this->dongTraHang($khoa, $data['items'] ?? []);

            if ($lyDo === RefundReason::Returned && $dongTra === []) {
                throw new RefundException('Lý do "Khách trả hàng" phải ghi rõ hàng nào được trả về, bao nhiêu.');
            }

            $ma = $this->sinhMa();

            $refund = Refund::create([
                'order_id' => $khoa->id,
                'code' => $ma,
                'amount' => $soTien,
                'reason' => $lyDo,
                'note' => trim((string) ($data['note'] ?? '')) ?: null,
                'method' => $cach,
            ]);

            $refund->forceFill([
                'created_by' => Auth::id(),
                'created_by_name' => Auth::user()?->name,
                'gateway_request_id' => $cach->tuDong() ? $ma : null,
            ])->save();

            foreach ($dongTra as $dong) {
                $refund->items()->create($dong);
            }

            if (! $cach->tuDong()) {
                $this->hoanTat($refund, $khoa, $maGiaoDich);
            }

            return $refund;
        });

        if ($cach->tuDong()) {
            $this->goiMomo($refund);
        }

        $refund->refresh();

        $this->mailer->sendRefund($refund);

        $this->audit->log(
            'don-hang.hoan-tien',
            sprintf(
                'Hoàn tiền %s cho đơn %s: %s qua %s — %s',
                $refund->code,
                $order->order_number,
                \App\Services\Shop\Money::format((string) $refund->amount),
                $cach->label(),
                $refund->status->label(),
            ),
            $order,
            [
                'code' => $refund->code,
                'amount' => (string) $refund->amount,
                'method' => $cach->value,
                'status' => $refund->status->value,
            ],
        );

        return $refund;
    }

    public function xacNhanDaHoan(Refund $refund, string $maGiaoDich): void
    {
        $maGiaoDich = trim($maGiaoDich);

        if ($maGiaoDich === '') {
            throw new RefundException('Nhập mã giao dịch hoàn tiền thấy trên cổng MoMo để xác nhận.');
        }

        DB::transaction(function () use ($refund, $maGiaoDich) {
            [$khoa, $phieu] = $this->khoaPhieuDangCho($refund);
            $this->hoanTat($phieu, $khoa, $maGiaoDich);
        });

        $this->mailer->sendRefund($refund->fresh());

        $this->audit->log('don-hang.xac-nhan-hoan-tien', 'Xác nhận đã hoàn '.$refund->code, $refund->order);
    }

    public function danhDauThatBai(Refund $refund): void
    {
        DB::transaction(function () use ($refund) {
            [, $phieu] = $this->khoaPhieuDangCho($refund);
            $phieu->forceFill(['status' => RefundStatus::Failed])->save();
        });

        $this->audit->log('don-hang.hoan-tien-that-bai', 'Đánh dấu không hoàn được '.$refund->code, $refund->order);
    }

    private function goiMomo(Refund $refund): void
    {
        $order = $refund->order()->first();
        $giaoDich = $this->giaoDichMomo($order);

        $ketQua = $this->momo->refund(
            $giaoDich,
            (int) $refund->amount,
            (string) $refund->gateway_request_id,
            'Hoan tien ' . $refund->code,
        );

        if ($ketQua === []) {
            Log::warning('Không nhận được trả lời từ MoMo khi hoàn tiền.', ['refund' => $refund->code]);

            return;
        }

        $refund->forceFill(['gateway_response' => $ketQua])->save();

        if ((string) ($ketQua['resultCode'] ?? '') !== '0') {
            $refund->forceFill(['status' => RefundStatus::Failed])->save();

            Log::warning('MoMo từ chối hoàn tiền.', [
                'refund' => $refund->code,
                'result_code' => $ketQua['resultCode'] ?? null,
                'message' => $ketQua['message'] ?? null,
            ]);

            return;
        }

        DB::transaction(function () use ($refund, $ketQua) {
            [$khoa, $phieu] = $this->khoaPhieuDangCho($refund);
            $this->hoanTat($phieu, $khoa, isset($ketQua['transId']) ? (string) $ketQua['transId'] : null);
        });
    }

    private function hoanTat(Refund $refund, Order $khoa, ?string $maGiaoDich): void
    {
        $refund->forceFill([
            'status' => RefundStatus::Completed,
            'completed_at' => now(),
            'reference' => $maGiaoDich ?? $refund->reference,
        ])->save();

        foreach ($refund->items()->with('orderItem')->get() as $dong) {
            if ($dong->restock) {
                $this->stock->congLai($dong->orderItem, $dong->quantity);
            }
        }

        $khoa->load('refunds');

        if (bccomp($khoa->refundedAmount(), $khoa->daThu(), 2) >= 0) {
            $khoa->payment_status = PaymentStatus::Refunded;
            $khoa->save();
        }

        app(\App\Services\Points\PointEarning::class)->hoanTien($refund);

        if ($khoa->payment_status === PaymentStatus::Refunded) {
            app(\App\Services\Points\PointLedger::class)->traDiemCuaDon($khoa);
        }
    }

    private function khoaPhieuDangCho(Refund $refund): array
    {
        $khoa = Order::whereKey($refund->order_id)->lockForUpdate()->firstOrFail();
        $phieu = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();

        if ($phieu->status !== RefundStatus::Pending) {
            throw new RefundException(sprintf(
                'Khoản hoàn %s đã ở trạng thái "%s", không còn chờ kết quả.',
                $phieu->code,
                $phieu->status->label(),
            ));
        }

        return [$khoa, $phieu];
    }

    private function dongTraHang(Order $khoa, array $items): array
    {
        $ket = [];

        foreach ($items as $idDong => $dong) {
            $soLuong = (int) ($dong['quantity'] ?? 0);

            if ($soLuong <= 0) {
                continue;
            }

            if ($khoa->status !== OrderStatus::Completed) {
                throw new RefundException(
                    'Chỉ nhận hàng trả về cho đơn đã giao. Hàng của đơn đã huỷ đã được cộng lại kho lúc huỷ.'
                );
            }

            $item = $khoa->items->firstWhere('id', (int) $idDong);

            if (! $item) {
                throw new RefundException('Có dòng hàng không thuộc đơn này.');
            }

            $con = (int) $item->quantity - $this->soDaTra($item);

            if ($soLuong > $con) {
                throw new RefundException(sprintf(
                    '"%s" chỉ còn trả về được %d.',
                    $item->product_name,
                    max(0, $con),
                ));
            }

            $ket[] = [
                'order_item_id' => $item->id,
                'quantity' => $soLuong,
                'restock' => filter_var($dong['restock'] ?? false, FILTER_VALIDATE_BOOL),
            ];
        }

        return $ket;
    }

    private function sinhMa(): string
    {
        for ($lan = 0; $lan < 5; $lan++) {
            $ma = sprintf('HT-%s-%s', now()->format('ymd'), Str::upper(Str::random(4)));

            if (! Refund::where('code', $ma)->exists()) {
                return $ma;
            }
        }

        throw new RefundException('Không sinh được mã hoàn tiền, vui lòng thử lại.');
    }
}
