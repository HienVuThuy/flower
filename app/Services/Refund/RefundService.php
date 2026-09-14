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

/**
 * NƠI DUY NHẤT trả tiền lại cho khách.
 * ============================================================
 * BỐN LUẬT KHÔNG ĐƯỢC PHÁ:
 *
 *   1. KHÔNG HOÀN QUÁ SỐ KHÁCH ĐÃ TRẢ, kể cả khi hai người bấm cùng lúc.
 *      Khoá dòng đơn rồi mới cộng các lần hoàn cũ; kiểm bằng số đã nạp
 *      từ trước thì hai request cùng thấy "còn 300.000₫" và cùng hoàn.
 *
 *   2. LẦN HOÀN QUA MOMO ĐƯỢC GHI TRƯỚC KHI GỌI MOMO, ở trạng thái "chưa
 *      rõ kết quả". Gọi trước rồi mới ghi thì một lỗi giữa hai bước là
 *      tiền đã đi mà sổ không có dòng nào; và trong lúc chờ MoMo trả lời,
 *      số tiền đó phải đang bị giữ chỗ.
 *
 *   3. MẤT KẾT NỐI KHÔNG PHẢI LÀ THẤT BẠI. MoMo có thể đã hoàn rồi. Khoản
 *      đó ở lại "chưa rõ kết quả" cho tới khi người thật kiểm trên cổng
 *      MoMo và xác nhận; coi là thất bại thì tiền được nhả ra, admin bấm
 *      hoàn lần nữa, và khách nhận tiền hai lần.
 *
 *   4. HÀNG TRẢ VỀ CHỈ VÀO KHO KHI TIỀN ĐÃ HOÀN XONG, và chỉ phần được
 *      đánh dấu còn bán được. Chậu vỡ khách gửi về không phải hàng tồn.
 *
 * "Đã hoàn tiền" của đơn (`payment_status = refunded`) là HỆ QUẢ: đặt ở
 * đây khi tổng các lần hoàn xong bằng số khách đã trả. Không còn nút nào
 * đặt tay trạng thái đó.
 */
class RefundService
{
    /** MoMo không nhận yêu cầu hoàn dưới mức này. */
    public const MOMO_TOI_THIEU = 1000;

    public function __construct(
        private readonly MomoGateway $momo,
        private readonly StockReturn $stock,
        private readonly ActivityLogger $audit,
        private readonly OrderMailer $mailer,
    ) {
    }

    /**
     * Vì sao đơn này không hoàn tiền được, hoặc null nếu được.
     *
     * Trả về CÂU CHỮ chứ không phải true/false: giao diện dùng đúng câu này
     * để nói với admin, thay vì ẩn biểu mẫu mà không giải thích.
     */
    public function lyDoKhongHoanDuoc(Order $order): ?string
    {
        if (! in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Completed], true)) {
            return 'Chỉ hoàn tiền cho đơn đã huỷ hoặc đã giao. Đơn đang xử lý thì huỷ đơn trước.';
        }

        if ($order->payment_status === PaymentStatus::Refunded) {
            return 'Đơn này đã được hoàn đủ tiền.';
        }

        if ($order->payment_status !== PaymentStatus::Paid) {
            return 'Khách chưa trả tiền cho đơn này nên không có gì để hoàn.';
        }

        if (bccomp($order->refundableAmount(), '0', 2) <= 0) {
            return 'Toàn bộ số tiền của đơn đã được hoàn, hoặc đang chờ kết quả hoàn.';
        }

        return null;
    }

    /**
     * Giao dịch MoMo thành công của đơn — thứ API hoàn tiền cần.
     *
     * Không có nó thì không hoàn qua MoMo được: đơn COD, hoặc đơn được
     * đánh dấu đã trả bằng tay.
     */
    public function giaoDichMomo(Order $order): ?PaymentTransaction
    {
        return $order->transactions()
            ->where('gateway', MomoGateway::GATEWAY)
            ->where('status', PaymentTransactionStatus::Paid->value)
            ->whereNotNull('transaction_id')
            ->latest('id')
            ->first();
    }

    /**
     * Cách hoàn dùng được cho đơn.
     *
     * @return list<RefundMethod>
     */
    public function cachHoan(Order $order): array
    {
        $ds = [];

        if ($this->momo->configured() && $this->giaoDichMomo($order)) {
            $ds[] = RefundMethod::Momo;
        }

        $ds[] = RefundMethod::BankTransfer;
        $ds[] = RefundMethod::Cash;

        return $ds;
    }

    /** Số lượng của một dòng đơn đã trả về (không tính lần hoàn thất bại). */
    public function soDaTra(OrderItem $item): int
    {
        return (int) RefundItem::query()
            ->where('order_item_id', $item->id)
            ->whereHas('refund', fn ($q) => $q->where('status', '!=', RefundStatus::Failed->value))
            ->sum('quantity');
    }

    /**
     * Ghi một lần hoàn tiền, và với MoMo thì gọi luôn cổng.
     *
     * @param  array{amount: int|string, reason: string, method: string,
     *               reference?: ?string, note?: ?string,
     *               items?: array<int|string, array{quantity?: mixed, restock?: mixed}>}  $data
     *               `items` đánh khoá theo id dòng đơn.
     *
     * @throws RefundException
     */
    public function hoan(Order $order, array $data): Refund
    {
        $cach = RefundMethod::from((string) $data['method']);
        $lyDo = RefundReason::from((string) $data['reason']);

        // Tiền Việt không có phần lẻ; biểu mẫu đã kiểm là số nguyên.
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
                if (! $this->momo->configured() || ! $this->giaoDichMomo($khoa)) {
                    throw new RefundException('Đơn này không có giao dịch MoMo thành công nên không hoàn qua MoMo được.');
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
                // Mã yêu cầu gửi MoMo lấy luôn mã phiếu: đã UNIQUE, và admin
                // tra trên cổng MoMo bằng đúng mã họ thấy trên màn hình.
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

        /*
         * BÁO KHÁCH — SAU transaction, và chỉ khi tiền đã đi.
         *
         * Thư gửi rồi không rút lại được; dữ liệu thì cuộn lại được. Nên
         * thứ không rút lại được đi sau cùng, cùng lý do với thư đổi trạng
         * thái trong OrderService.
         */
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

    /**
     * Người thật đã kiểm trên cổng MoMo: khoản "chưa rõ kết quả" ĐÃ hoàn.
     *
     * @throws RefundException
     */
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

    /**
     * Người thật đã kiểm: khoản "chưa rõ kết quả" KHÔNG hoàn. Nhả số tiền.
     *
     * @throws RefundException
     */
    public function danhDauThatBai(Refund $refund): void
    {
        DB::transaction(function () use ($refund) {
            [, $phieu] = $this->khoaPhieuDangCho($refund);
            $phieu->forceFill(['status' => RefundStatus::Failed])->save();
        });

        $this->audit->log('don-hang.hoan-tien-that-bai', 'Đánh dấu không hoàn được '.$refund->code, $refund->order);
    }

    /* ================= BÊN TRONG ================= */

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
            // Luật 3: không biết thì để nguyên "chưa rõ kết quả".
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

    /**
     * Chốt một lần hoàn: đánh dấu xong, cộng hàng còn bán được vào kho, và
     * đặt "đã hoàn tiền" cho đơn nếu đã hoàn đủ.
     *
     * PHẢI GỌI TRONG TRANSACTION, với dòng đơn đã khoá.
     */
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

        if (bccomp($khoa->refundedAmount(), (string) $khoa->grand_total, 2) >= 0) {
            $khoa->payment_status = PaymentStatus::Refunded;
            $khoa->save();
        }

        /*
         * TRỪ ĐIỂM của đơn đã được cộng — TRONG transaction: tiền đã hoàn
         * xong thì điểm phải trừ cùng lúc, không để một nửa. Xem PointEarning.
         */
        app(\App\Services\Points\PointEarning::class)->hoanTien($refund);
    }

    /**
     * Khoá đơn rồi phiếu, và đòi phiếu còn đang "chưa rõ kết quả".
     *
     * Hai người cùng xử lý một khoản đang chờ — một người xác nhận, một
     * người đánh dấu thất bại — thì người thứ hai phải bị chặn, không được
     * ghi đè kết quả của người thứ nhất.
     *
     * @return array{0: Order, 1: Refund}
     */
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

    /**
     * Đổi dữ liệu biểu mẫu thành các dòng hàng trả về.
     *
     * KHÔNG TIN id dòng đơn từ biểu mẫu: tra trong CHÍNH đơn đang khoá.
     * Không làm vậy thì một id bịa trả được hàng của đơn người khác về kho.
     *
     * @param  array<int|string, array{quantity?: mixed, restock?: mixed}>  $items
     * @return list<array{order_item_id: int, quantity: int, restock: bool}>
     */
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

    /** Mã HT-260930-A3F2. Không dùng id tự tăng: nó lộ số lần hoàn tiền. */
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
