<?php

namespace App\Services\Shipping;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Audit\ActivityLogger;
use App\Services\Order\OrderException;
use App\Services\Order\OrderService;
use Illuminate\Support\Facades\Log;

/** Hỏi GHN xem hàng đang ở đâu, rồi cập nhật đơn theo câu trả lời. */
class GhnStatusSync
{
    private const KET_THUC = ['delivered', 'returned', 'cancel', 'lost'];

    private const DANG_GIAO = ['picked', 'storing', 'transporting', 'sorting', 'delivering'];

    public function __construct(
        private readonly GHNService $ghn,
        private readonly OrderService $orders,
        private readonly ActivityLogger $nhatKy,
    ) {
    }

    public function syncAll(): array
    {
        $ketQua = ['da_hoi' => 0, 'da_doi' => 0, 'loi' => 0];

        if (! $this->ghn->configured()) {
            return $ketQua;
        }

        Order::query()
            ->whereNotNull('ghn_order_code')
            ->where(fn ($q) => $q->whereNull('shipping_status')
                ->orWhereNotIn('shipping_status', self::KET_THUC))
            ->chunkById(50, function ($donHang) use (&$ketQua) {
                foreach ($donHang as $order) {
                    $ketQua['da_hoi']++;

                    try {
                        if ($this->syncOne($order)) {
                            $ketQua['da_doi']++;
                        }
                    } catch (\Throwable $e) {
                        $ketQua['loi']++;

                        Log::warning('Đồng bộ vận đơn GHN thất bại', [
                            'order_number' => $order->order_number,
                            'ghn_order_code' => $order->ghn_order_code,
                            'loi' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $ketQua;
    }

    public function syncOne(Order $order): bool
    {
        $chiTiet = $this->ghn->orderDetail((string) $order->ghn_order_code);

        if ((int) ($chiTiet['code'] ?? 0) !== 200) {
            throw new \RuntimeException(sprintf(
                'GHN không trả lời được vận đơn %s: %s',
                $order->ghn_order_code,
                $chiTiet['message'] ?? 'không rõ lý do',
            ));
        }

        $this->luuDuKienGiao($order, $chiTiet['data'] ?? []);

        $trangThai = $chiTiet['data']['status'] ?? null;

        if (! is_string($trangThai) || $trangThai === '') {
            return false;
        }

        if ($trangThai === $order->shipping_status) {
            return false;
        }

        $cu = $order->shipping_status;
        $order->shipping_status = $trangThai;
        $order->save();

        $this->nhatKy->log(
            'don-hang.dong-bo-van-don',
            sprintf(
                'GHN cập nhật vận đơn %s: %s → %s',
                $order->ghn_order_code,
                $cu ?? '(chưa có)',
                $trangThai,
            ),
            $order,
            ['truoc' => $cu, 'sau' => $trangThai],
        );

        $this->theoTrangThaiVanDon($order, $trangThai);

        return true;
    }

    private function luuDuKienGiao(Order $order, array $data): void
    {
        $tu = $data['leadtime_order']['from_estimate_date'] ?? $data['leadtime'] ?? null;
        $den = $data['leadtime_order']['to_estimate_date'] ?? $data['leadtime'] ?? null;

        $order->forceFill([
            'ghn_expected_from' => $this->thoiDiem($tu),
            'ghn_expected_to' => $this->thoiDiem($den),
        ])->save();
    }

    private function thoiDiem(mixed $raw): ?\Illuminate\Support\Carbon
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private function theoTrangThaiVanDon(Order $order, string $trangThai): void
    {
        if ($trangThai === 'delivered') {
            $this->daGiaoThanhCong($order);

            return;
        }

        if (in_array($trangThai, self::DANG_GIAO, true)) {
            $this->chuyenTrangThai($order, OrderStatus::Shipping);
        }
    }

    private function daGiaoThanhCong(Order $order): void
    {
        if ($order->payment_method === PaymentMethod::Cod
            && $order->payment_status === PaymentStatus::Unpaid) {

            $this->thu($order, fn () => $this->orders->setPaymentStatus($order, PaymentStatus::Paid));

            $this->nhatKy->log(
                'don-hang.tu-dong-thanh-toan',
                sprintf(
                    'Tự động ghi nhận đã thu tiền COD cho đơn %s (GHN báo đã giao thành công).',
                    $order->order_number,
                ),
                $order,
            );
        }

        $this->chuyenTrangThai($order, OrderStatus::Completed);
    }

    private function chuyenTrangThai(Order $order, OrderStatus $dich): void
    {
        if ($order->status === $dich || $order->status->nextStates() === []) {
            return;
        }

        $conLai = count(OrderStatus::cases());

        while ($order->status !== $dich && $conLai-- > 0) {
            $buocTiep = $this->buocKeTiep($order->status);

            if ($buocTiep === null) {
                return;
            }

            $truoc = $order->status;

            $this->thu($order, fn () => $this->orders->changeStatus($order, $buocTiep));

            $order->refresh();

            if ($order->status === $truoc) {
                return;
            }
        }
    }

    private function buocKeTiep(OrderStatus $hienTai): ?OrderStatus
    {
        foreach ($hienTai->nextStates() as $ungVien) {
            if ($ungVien !== OrderStatus::Cancelled) {
                return $ungVien;
            }
        }

        return null;
    }

    private function thu(Order $order, callable $viec): void
    {
        try {
            $viec();
        } catch (OrderException $e) {
            Log::info('Bỏ qua một bước đồng bộ vận đơn', [
                'order_number' => $order->order_number,
                'ly_do' => $e->getMessage(),
            ]);
        }
    }
}
