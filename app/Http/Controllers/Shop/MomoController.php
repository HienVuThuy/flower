<?php

namespace App\Http\Controllers\Shop;

use App\Enums\MomoFlow;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Concerns\AuthorizesOrderAccess;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Order\OrderException;
use App\Services\Installment\InstallmentService;
use App\Services\Order\OrderService;
use App\Services\Order\PaidOrderFulfilment;
use App\Services\Payment\MomoGateway;
use App\Services\Payment\PaymentException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Thanh toán MoMo. */
class MomoController extends Controller
{
    use AuthorizesOrderAccess;

    public function __construct(
        private readonly MomoGateway $momo,
        private readonly OrderService $orders,
    ) {
    }

    public function start(Request $request, Order $order): RedirectResponse
    {
        return $this->chuyenSangMomo($request, $order);
    }

    public function payAgain(Request $request, Order $order): RedirectResponse
    {
        return $this->chuyenSangMomo($request, $order);
    }

    private function chuyenSangMomo(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrderAccess($order);

        if ($order->payment_method !== PaymentMethod::Momo) {
            return redirect()
                ->route('shop.orders.show', $order)
                ->with('error', 'Đơn này không thanh toán bằng MoMo.');
        }

        if ($order->payment_status === PaymentStatus::Paid) {
            return redirect()
                ->route('shop.orders.show', $order)
                ->with('success', 'Đơn này đã thanh toán rồi.');
        }

        if (! $order->isCancellableByCustomer() && $order->status->isFinal()) {
            return redirect()
                ->route('shop.orders.show', $order)
                ->with('error', 'Đơn đã kết thúc nên không thanh toán được nữa.');
        }

        $flow = MomoFlow::tryFrom((string) $request->query('cach', ''))
            ?? MomoFlow::macDinh();

        try {
            return redirect()->away($this->momo->createPayment($order, $flow));
        } catch (PaymentException $e) {
            return redirect()
                ->route('shop.orders.show', $order)
                ->with('error', $e->getMessage());
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        $payload = $request->query();

        if (! $this->momo->verifySignature($payload)) {
            Log::warning('Callback MoMo sai chữ ký.', ['order_id' => $payload['orderId'] ?? null]);

            return redirect()
                ->route('shop.orders.index')
                ->with('error', 'Không xác thực được kết quả thanh toán từ MoMo.');
        }

        $ketQua = $this->ghiNhanThanhToan($payload);
        $order = $this->timDon($payload);

        $ve = $order
            ? redirect()->route('shop.orders.show', $order)
            : redirect()->route('shop.orders.index');

        return match ($ketQua) {
            'paid', 'already' => $ve->with(
                'success',
                'Thanh toán MoMo thành công. Đơn hàng đã được xác nhận và đang chuẩn bị giao.',
            ),
            'ky' => $ve->with('success', 'Đã nhận tiền kỳ trả góp qua MoMo. Cửa hàng giao hàng khi bạn trả đủ các kỳ.'),
            'mismatch' => $ve->with('error', 'Số tiền MoMo báo về không khớp với đơn hàng. Cửa hàng sẽ liên hệ với bạn.'),
            'cancelled' => $ve->with('error', 'Đơn đã huỷ nên không ghi nhận thanh toán. Cửa hàng sẽ liên hệ để hoàn tiền.'),
            'invalid' => $ve->with('error', 'Không tìm thấy lượt thanh toán tương ứng.'),
            default => $ve->with('error', $this->loiCuaMomo($payload)),
        };
    }

    public function ipn(Request $request): JsonResponse
    {
        $payload = $request->all();

        if ($this->momo->verifySignature($payload)) {
            $this->ghiNhanThanhToan($payload);
        } else {
            Log::warning('IPN MoMo sai chữ ký.', ['order_id' => $payload['orderId'] ?? null]);
        }

        return response()->json(['message' => 'Received']);
    }

    private function ghiNhanThanhToan(array $payload): string
    {
        $ketQua = DB::transaction(function () use ($payload): string {
            $transaction = PaymentTransaction::where('gateway', MomoGateway::GATEWAY)
                ->where('gateway_order_id', (string) ($payload['orderId'] ?? ''))
                ->lockForUpdate()
                ->first();

            if (! $transaction) {
                return 'invalid';
            }

            if ($transaction->isPaid()) {
                return 'already';
            }

            if (! $this->momo->isSuccessful($payload)) {
                $this->momo->markFailed($transaction, $payload);

                return 'failed';
            }

            $order = Order::lockForUpdate()->find($transaction->order_id);

            if (! $order) {
                return 'invalid';
            }

            if ((int) round((float) $transaction->amount) !== (int) ($payload['amount'] ?? 0)) {
                $this->momo->markFailed($transaction, $payload);

                Log::error('Số tiền MoMo báo về không khớp.', [
                    'order' => $order->order_number,
                    'don' => (string) $transaction->amount,
                    'momo' => $payload['amount'] ?? null,
                ]);

                return 'mismatch';
            }

            $this->momo->markPaid($transaction, $payload);

            if ($transaction->installment_payment_id !== null) {
                $ky = app(InstallmentService::class)->ghiNhanKy((int) $transaction->installment_payment_id);

                if ($ky === 'already') {
                    Log::warning('Kỳ trả góp đã được trả bằng lượt khác.', [
                        'order' => $order->order_number,
                        'giao_dich' => $transaction->gateway_order_id,
                    ]);
                }

                return match ($ky) {
                    'completed' => 'paid',
                    'closed' => 'cancelled',
                    default => 'ky',
                };
            }

            try {
                $this->orders->setPaymentStatus($order, PaymentStatus::Paid);
            } catch (OrderException $e) {
                Log::error('Đơn không nhận được thanh toán MoMo.', [
                    'order' => $order->order_number,
                    'ly_do' => $e->getMessage(),
                ]);

                return 'cancelled';
            }

            return 'paid';
        });

        if (in_array($ketQua, ['paid', 'already'], true)) {
            $this->hoanTatSauThanhToan($payload);
        }

        return $ketQua;
    }

    private function hoanTatSauThanhToan(array $payload): void
    {
        $order = $this->timDon($payload);

        if (! $order) {
            return;
        }

        app(PaidOrderFulfilment::class)->sauKhiTraTien($order, 'Đã nhận thanh toán qua MoMo.');
    }

    private function timDon(array $payload): ?Order
    {
        $ma = $this->momo->orderNumberFrom($payload);

        return $ma ? Order::where('order_number', $ma)->first() : null;
    }

    private function loiCuaMomo(array $payload): string
    {
        $ly = trim((string) ($payload['message'] ?? ''));

        return 'Thanh toán MoMo chưa thành công'
            . ($ly === '' ? '' : ': ' . $ly)
            . '. Bạn có thể bấm "Thanh toán lại" ở trang đơn hàng.';
    }
}
