<?php

namespace App\Http\Controllers\Shop;

use App\Enums\MomoFlow;
use App\Enums\OrderStatus;
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
use App\Services\Shipping\GHNOrderService;
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
 *                                │
 *                                └─► hoanTatSauThanhToan()
 *                                      ├─ xác nhận đơn (gửi thư cho khách)
 *                                      └─ tạo vận đơn GHN
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
        private readonly GHNOrderService $ghn,
    ) {
    }

    /** Lần trả đầu tiên, ngay sau khi đặt hàng. */
    public function start(Request $request, Order $order): RedirectResponse
    {
        return $this->chuyenSangMomo($request, $order);
    }

    /**
     * Trả lại sau một lần thất bại.
     *
     * KHÔNG tạo đơn mới — vẫn là đơn cũ, chỉ thêm một lượt giao dịch.
     */
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

        /*
         * CÁCH TRẢ TIỀN ĐỌC TỪ URL, giá trị lạ rơi về mặc định.
         *
         * Không abort(404): người ta chép link cho nhau, và một tham số
         * hỏng không đáng để cả lượt thanh toán biến mất — cùng nguyên
         * tắc đã dùng cho bộ lọc sản phẩm.
         */
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
            'paid', 'already' => $ve->with(
                'success',
                'Thanh toán MoMo thành công. Đơn hàng đã được xác nhận và đang chuẩn bị giao.',
            ),
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

        /*
         * XÁC NHẬN ĐƠN VÀ TẠO VẬN ĐƠN — SAU KHI TRANSACTION ĐÃ COMMIT.
         *
         * Hai việc này gọi ra ngoài (gửi thư, gọi API GHN). Để bên trong
         * transaction thì một cuộc gọi HTTP chậm sẽ giữ khoá hàng của đơn
         * suốt thời gian đó, và tệ hơn: thư có thể đã bay đi trong khi
         * transaction sau đó bị cuộn lại.
         *
         * Chạy cả với 'already' chứ không chỉ 'paid': nếu lần callback
         * đầu ghi tiền xong nhưng GHN lỗi mạng, thì IPN về sau còn một
         * cơ hội nữa. Cả hai bước bên trong đều tự bỏ qua nếu đã làm rồi.
         */
        if (in_array($ketQua, ['paid', 'already'], true)) {
            $this->hoanTatSauThanhToan($payload);
        }

        return $ketQua;
    }

    /**
     * Việc phải làm ngay sau khi tiền về: xác nhận đơn, rồi bàn giao GHN.
     *
     * ============================================================
     * VÌ SAO ĐƠN ĐÃ TRẢ TIỀN THÌ TỰ ĐỘNG, CÒN COD THÌ KHÔNG.
     *
     * Tạo vận đơn là CAM KẾT với GHN: họ cử người tới lấy hàng và tính
     * tiền cửa hàng. Với đơn COD, thứ duy nhất đứng sau lời hứa của
     * khách là lời hứa đó — nên cửa hàng phải nhìn đơn trước khi cam kết.
     *
     * Đơn đã trả tiền thì khác hẳn: khách đã bỏ tiền ra, và bắt họ đợi
     * một nhân viên bấm nút là kéo dài thời gian giao hàng vì một bước
     * không còn tác dụng gì. Vì thế xác nhận và bàn giao ngay.
     *
     * KHÔNG BAO GIỜ NÉM LỖI RA NGOÀI. Tiền đã ghi nhận xong rồi; một
     * cuộc gọi GHN hỏng không được phép biến thành trang lỗi trước mặt
     * khách vừa trả tiền. Hỏng thì ghi log, và nút tạo vận đơn thủ công
     * ở trang quản trị vẫn còn nguyên.
     *
     * @param  array<string, mixed>  $payload
     */
    private function hoanTatSauThanhToan(array $payload): void
    {
        $order = $this->timDon($payload);

        if (! $order) {
            return;
        }

        // Đơn đã bị huỷ trước khi tiền về: không xác nhận, không bàn
        // giao. Trường hợp này cần người thật xử lý hoàn tiền.
        if ($order->status === OrderStatus::Cancelled) {
            return;
        }

        if ($order->status === OrderStatus::Pending) {
            try {
                $this->orders->changeStatus(
                    $order,
                    OrderStatus::Confirmed,
                    'Đã nhận thanh toán qua MoMo.',
                    tuDong: true,
                );
            } catch (OrderException $e) {
                Log::warning('Không tự xác nhận được đơn sau thanh toán.', [
                    'order' => $order->order_number,
                    'ly_do' => $e->getMessage(),
                ]);
            }
        }

        /*
         * KHÔNG kiểm "đã có vận đơn chưa" ở đây.
         *
         * GHNOrderService::create() đã tự chặn: có mã rồi thì nó trả về
         * ngay, không gọi ra GHN. Kiểm lại lần nữa ở đây là dựng bản thứ
         * hai của cùng một luật — và bản thứ hai sẽ lệch vào đúng ngày ai
         * đó sửa bản thứ nhất.
         *
         * Đã kiểm bằng cách bỏ chốt cũ đi: bài kiểm thử đếm số lời gọi
         * GHN vẫn xanh, tức là lớp dưới thật sự đang gánh việc đó.
         */
        try {
            $ketQua = $this->ghn->create($order->refresh());

            if (($ketQua['code'] ?? null) !== 200) {
                Log::error('Không tạo được vận đơn GHN sau thanh toán MoMo.', [
                    'order' => $order->order_number,
                    'ghn' => $ketQua['message'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Lỗi khi tạo vận đơn GHN sau thanh toán MoMo.', [
                'order' => $order->order_number,
                'exception' => $e->getMessage(),
            ]);
        }
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
