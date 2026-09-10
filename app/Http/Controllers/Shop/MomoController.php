<?php

namespace App\Http\Controllers\Shop;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Concerns\AuthorizesOrderAccess;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Order\OrderException;
use App\Services\Order\OrderService;
use App\Services\Payment\MomoGateway;
use App\Services\Payment\PaymentException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Thanh toán MoMo.
 * ============================================================
 *   đơn đã tạo
 *      └─ start / payAgain ─► tạo bản ghi giao dịch ─► gọi API MoMo
 *                                                        └─► payUrl
 *      (khách trả tiền trên trang MoMo)
 *                                │
 *          ┌─────────────────────┴─────────────────────┐
 *      callback (trình duyệt quay về)            ipn (MoMo gọi máy chủ)
 *          └──────────────► ghiNhanThanhToan() ◄──────┘
 *
 * HAI ĐƯỜNG VỀ, MỘT KẾT QUẢ. Cả hai đều phải kiểm chữ ký rồi mới ghi,
 * và cả hai đi qua cùng một hàm nên chạy đường nào kết quả cũng như
 * nhau. Callback là để KHÁCH nhìn thấy; IPN là đường đáng tin, vì nó
 * không đi qua trình duyệt của khách.
 *
 * IPN không tới được localhost — MoMo gọi từ Internet vào. Khi học ở
 * nhà, luồng vẫn chạy đủ nhờ callback; ghiNhanThanhToan() viết sao cho
 * gọi mấy lần cũng ra một kết quả nên khi có IPN thật cũng không hỏng.
 */
class MomoController extends Controller
{
    use AuthorizesOrderAccess;

    public function __construct(
        private readonly MomoGateway $momo,
        private readonly OrderService $orders,
    ) {
    }

    /** Lần trả đầu tiên, ngay sau khi đặt hàng. */
    public function start(Order $order): RedirectResponse
    {
        return $this->chuyenSangMomo($order);
    }

    /**
     * Trả lại sau một lần thất bại.
     *
     * KHÔNG tạo đơn mới — vẫn là đơn cũ, chỉ thêm một lượt giao dịch.
     */
    public function payAgain(Order $order): RedirectResponse
    {
        return $this->chuyenSangMomo($order);
    }

    private function chuyenSangMomo(Order $order): RedirectResponse
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

        try {
            return redirect()->away($this->momo->createPayment($order));
        } catch (PaymentException $e) {
            return redirect()
                ->route('shop.orders.show', $order)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Trình duyệt khách quay về sau khi rời trang MoMo.
     *
     * KHÔNG tin vào việc khách có mặt ở đây: đường dẫn này ai gõ cũng
     * được. Thứ quyết định là chữ ký trong gói tin.
     */
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
            'paid', 'already' => $ve->with('success', 'Thanh toán MoMo thành công.'),
            'mismatch' => $ve->with('error', 'Số tiền MoMo báo về không khớp với đơn hàng. Cửa hàng sẽ liên hệ với bạn.'),
            'cancelled' => $ve->with('error', 'Đơn đã huỷ nên không ghi nhận thanh toán. Cửa hàng sẽ liên hệ để hoàn tiền.'),
            'invalid' => $ve->with('error', 'Không tìm thấy lượt thanh toán tương ứng.'),
            default => $ve->with('error', $this->loiCuaMomo($payload)),
        };
    }

    /**
     * MoMo gọi thẳng vào máy chủ, không qua trình duyệt khách.
     *
     * Luôn trả 204 kể cả khi gói tin hỏng: mã lỗi làm MoMo gọi lại nhiều
     * lần, mà gói tin sai chữ ký thì gọi bao nhiêu lần cũng vẫn sai.
     */
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

    /**
     * Ghi kết quả của một gói tin ĐÃ XÁC MINH CHỮ KÝ.
     *
     * GỌI MẤY LẦN CŨNG RA MỘT KẾT QUẢ. Callback và IPN có thể về gần như
     * đồng thời cho cùng một lượt; `lockForUpdate` xếp chúng thành hàng
     * và lần thứ hai thấy giao dịch đã `paid` nên dừng lại.
     *
     * @param  array<string, mixed>  $payload
     */
    private function ghiNhanThanhToan(array $payload): string
    {
        return DB::transaction(function () use ($payload): string {
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

            /*
             * SỐ TIỀN PHẢI KHỚP.
             *
             * MoMo trả về số tiền đã thu; nếu nó khác số của đơn thì
             * hoặc gói tin bị sửa, hoặc đã có nhầm lẫn ở đâu đó. Ghi
             * nhận đã thanh toán trong tình huống đó là để phần mềm tự
             * xác nhận một số tiền nó không kiểm được.
             */
            if ((int) round((float) $transaction->amount) !== (int) ($payload['amount'] ?? 0)) {
                $this->momo->markFailed($transaction, $payload);

                Log::error('Số tiền MoMo báo về không khớp.', [
                    'order' => $order->order_number,
                    'don' => (string) $transaction->amount,
                    'momo' => $payload['amount'] ?? null,
                ]);

                return 'mismatch';
            }

            // Tiền ĐÃ về thật, nên lượt giao dịch phải ghi đúng như vậy
            // kể cả khi đơn không nhận được nữa.
            $this->momo->markPaid($transaction, $payload);

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
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function timDon(array $payload): ?Order
    {
        $ma = $this->momo->orderNumberFrom($payload);

        return $ma ? Order::where('order_number', $ma)->first() : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function loiCuaMomo(array $payload): string
    {
        $ly = trim((string) ($payload['message'] ?? ''));

        return 'Thanh toán MoMo chưa thành công'
            . ($ly === '' ? '' : ': ' . $ly)
            . '. Bạn có thể bấm "Thanh toán lại" ở trang đơn hàng.';
    }
}
