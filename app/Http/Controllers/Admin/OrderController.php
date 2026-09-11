<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Http\Controllers\Admin\Concerns\SortsAdminList;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Shipping\GHNOrderService;
use App\Services\Order\OrderException;
use App\Services\Order\OrderService;
use App\Services\Refund\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    use LogsAdminActivity;
    use SortsAdminList;

    public function __construct(
        private readonly OrderService $orders,
    ) {
    }

    public function index(Request $request): View
    {
        $status = $request->query('status');

        // Chỉ nhận giá trị nằm trong enum; tham số lạ thì coi như không lọc.
        if ($status !== null && OrderStatus::tryFrom($status) === null) {
            $status = null;
        }

        return view('admin.orders.index', [
            'orders' => Order::query()
                ->status($status)

                /*
                 * TÌM THEO MÃ ĐƠN, SỐ ĐIỆN THOẠI HOẶC TÊN NGƯỜI NHẬN.
                 *
                 * Ba cột này vì đó là ba thứ khách đọc qua điện thoại khi
                 * gọi hỏi đơn. Số điện thoại là cột hữu ích nhất: khách
                 * nhớ số của mình, ít khi nhớ mã đơn.
                 *
                 * Bỏ dấu gạch trong từ khoá trước khi so mã đơn — khách
                 * đọc "FP 260831 ABCD" hoặc "FP260831ABCD" đều phải ra.
                 */
                ->when($request->filled('q'), function ($query) use ($request) {
                    $tu = trim((string) $request->query('q'));
                    $ma = preg_replace('/[^A-Za-z0-9]/', '', $tu) ?? '';

                    $query->where(function ($q) use ($tu, $ma) {
                        $q->where('recipient_phone', 'like', '%'.$tu.'%')
                            ->orWhere('recipient_name', 'like', '%'.$tu.'%')
                            ->orWhereRaw(
                                "REPLACE(order_number, '-', '') LIKE ?",
                                ['%'.$ma.'%'],
                            );
                    });
                })

                ->when(
                    $request->filled('payment'),
                    fn ($q) => $q->where('payment_status', $request->string('payment'))
                )

                /*
                 * LỌC THEO VẬN ĐƠN.
                 *
                 * Đơn trả qua MoMo tự tạo vận đơn ngay sau khi thanh
                 * toán; đơn COD thì KHÔNG — phải có người bấm. Đơn quên
                 * bấm nằm ở "Đã xác nhận", trông y hệt đơn đang chạy, và
                 * không có cách nào lọc ra.
                 *
                 * "cho-tao" dùng CHUNG scope với dòng việc ở trang Tổng
                 * quan — xem Order::scopeAwaitingWaybill() để biết vì sao
                 * không lọc trần "chưa có mã".
                 *
                 * SO VỚI CHUỖI CỐ ĐỊNH chứ không dùng filled(): tham số
                 * lạ thì coi như không lọc, không đoán ý.
                 */
                ->when(
                    $request->query('van_don') === 'cho-tao',
                    fn ($q) => $q->awaitingWaybill()
                )
                // Dòng việc "hoàn tiền MoMo chưa rõ kết quả" ở trang Tổng quan.
                ->when(
                    $request->query('hoan_tien') === 'chua-ro',
                    fn ($q) => $q->refundPending()
                )
                ->when(
                    $request->query('van_don') === 'roi',
                    fn ($q) => $q->whereNotNull('ghn_order_code')
                )

                /*
                 * LỌC THEO KHOẢNG NGÀY.
                 *
                 * whereDate chứ không phải where: `created_at` có cả giờ,
                 * nên `where('created_at', '<=', '2026-08-31')` bỏ sót mọi
                 * đơn đặt trong ngày 31 — đúng ngày admin đang muốn xem.
                 */
                ->when(
                    $request->filled('tu_ngay'),
                    fn ($q) => $q->whereDate('created_at', '>=', $request->date('tu_ngay'))
                )
                ->when(
                    $request->filled('den_ngay'),
                    fn ($q) => $q->whereDate('created_at', '<=', $request->date('den_ngay'))
                )

                // withCount thay vì nạp cả quan hệ items: danh sách chỉ
                // cần CON SỐ, nạp hết dòng đơn là N+1 vô ích.
                ->withCount('items')
                ->tap(fn ($q) => $this->applySort($q, $request, [
                    'ma' => 'order_number',
                    'tien' => 'grand_total',
                    'trang-thai' => 'status',
                    'thanh-toan' => 'payment_status',
                    'ngay' => 'created_at',
                ], fn ($q) => $q->latest()))

                ->paginate(20)
                ->withQueryString(),
            'currentStatus' => $status,
            'statuses' => OrderStatus::cases(),
            'counts' => $this->countsByStatus(),
        ]);
    }

    public function show(Order $order, RefundService $refunds): View
    {
        $order->load('items', 'user', 'statusEvents.changedBy', 'invoice', 'transactions', 'refunds.items.orderItem', 'refunds.createdBy');

        return view('admin.orders.show', [
            // statusEvents.changedBy nạp sẵn: dòng thời gian hiện tên người
            // thực hiện ở mỗi mốc, không nạp thì mỗi mốc một truy vấn.
            'order' => $order,

            /*
             * HOÀN TIỀN: một câu "vì sao không hoàn được" (null nếu được),
             * các cách hoàn dùng được, và số còn trả về được của từng dòng.
             * Tính sẵn ở đây để view không phải biết luật nào.
             */
            'refundBlocked' => $refunds->lyDoKhongHoanDuoc($order),
            'refundMethods' => $refunds->cachHoan($order),
            'refundReasons' => \App\Enums\RefundReason::choTrangThai($order->status),
            'returnable' => $order->items->mapWithKeys(fn ($item) => [
                $item->id => (int) $item->quantity - $refunds->soDaTra($item),
            ]),

            /*
             * Đơn đã huỷ mà khách đã trả tiền = cửa hàng đang nợ khách.
             *
             * Hệ thống KHÔNG tự đánh dấu "đã hoàn tiền": nó không biết
             * ai đó có thật sự chuyển khoản trả lại hay chưa. Việc của
             * phần mềm là nhắc; việc chuyển tiền là của con người.
             */
            'owesRefund' => $this->orders->owesRefund($order),
            /*
             * CHỈ BÀY NÚT MÀ BẤM VÀO ĐƯỢC.
             *
             * "Đã hoàn tiền" không còn là một nút — nó là hệ quả của các lần
             * hoàn ghi ở mục Hoàn tiền. "Gỡ đánh dấu" thì OrderService từ
             * chối với đơn đã giao hoặc đã có khoản hoàn; bày nút ra ở đó là
             * một nút lúc nào bấm cũng báo lỗi.
             */
            'paymentTargets' => array_values(array_filter(
                $order->payment_status->nextStates(),
                fn ($t) => $t !== PaymentStatus::Refunded
                    && ! ($t === PaymentStatus::Unpaid && (
                        $order->status === OrderStatus::Completed
                        || $order->refunds->contains(fn ($r) => $r->status->giuChoTien())
                    )),
            )),
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'cancel_reason' => ['nullable', 'string', 'max:255'],
        ], [], [
            'status' => 'trạng thái',
            'cancel_reason' => 'lý do huỷ',
        ]);

        try {
            $this->orders->changeStatus(
                $order,
                OrderStatus::from($validated['status']),
                $validated['cancel_reason'] ?? null,
            );
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã cập nhật trạng thái đơn hàng.');
    }

    /**
     * Đổi trạng thái thanh toán.
     *
     * MỘT ROUTE CHO CẢ BA THAO TÁC (ghi nhận đã trả / hoàn tiền / sửa
     * bấm nhầm), không phải ba route. Luật chuyển trạng thái nằm trọn
     * trong PaymentStatus + OrderService; thêm route là thêm chỗ để
     * quên một phép kiểm.
     */
    public function updatePayment(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'payment_status' => ['required', Rule::enum(PaymentStatus::class)],
        ], [], ['payment_status' => 'trạng thái thanh toán']);

        $target = PaymentStatus::from($data['payment_status']);

        try {
            $this->orders->setPaymentStatus($order, $target);
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', match ($target) {
            PaymentStatus::Paid => 'Đã ghi nhận đơn hàng đã thanh toán.',
            PaymentStatus::Refunded => 'Đã ghi nhận hoàn tiền cho khách.',
            PaymentStatus::Unpaid => 'Đã gỡ đánh dấu thanh toán.',
        });
    }

    /**
     * Ghi chú nội bộ của đơn.
     *
     * CHỈ CỬA HÀNG ĐỌC — khách không bao giờ thấy. Đây là chỗ ghi những
     * thứ đơn hàng không có ô nào chứa: "đã gọi 2 lần không nghe",
     * "khách hẹn giao sau 17h", "shipper báo nhà khoá cửa".
     *
     * Cột `admin_note` có từ lúc dựng bảng `orders` nhưng CHƯA TỪNG có
     * giao diện nào ghi vào — nên mọi ghi chú kiểu này trước giờ nằm
     * trong đầu người trực, và mất khi đổi ca.
     */
    public function updateNote(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ], [
            'admin_note.max' => 'Ghi chú không được vượt quá 2000 ký tự.',
        ], ['admin_note' => 'ghi chú']);

        /*
         * Gán trực tiếp chứ không update([...]): `admin_note` cố ý nằm
         * ngoài $fillable của Order để không request nào của KHÁCH ghi
         * vào được. Ở đây là route quản trị, gán tay là đúng đường.
         */
        $order->admin_note = $data['admin_note'] ?: null;
        $order->save();

        return back()->with('success', 'Đã lưu ghi chú.');
    }

    /**
     * Số đơn theo từng trạng thái, cho các tab lọc.
     * Một truy vấn GROUP BY thay vì đếm riêng từng trạng thái.
     *
     * @return array<string, int>
     */
    private function countsByStatus(): array
    {
        return Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    /* ============ VẬN ĐƠN GIAO HÀNG NHANH ============ */

    /**
     * Bàn giao đơn cho GHN và nhận về mã vận đơn.
     *
     * NÚT NÀY DÀNH CHO ĐƠN CHƯA TRẢ TIỀN, và cho lúc tạo tự động hỏng.
     *
     * Tạo vận đơn là cam kết với GHN: họ sẽ cử người tới lấy hàng, và
     * tính tiền cửa hàng. Với đơn COD, thứ duy nhất đứng sau lời hứa của
     * khách là lời hứa đó — nên cửa hàng phải nhìn đơn trước khi cam
     * kết. Làm tự động lúc khách bấm đặt nghĩa là mọi đơn đặt nhầm, hết
     * hàng, hay huỷ sau ba phút đều thành một chuyến xe có thật.
     *
     * Đơn đã trả tiền qua cổng thì KHÁC: vận đơn được tạo tự động ngay
     * khi tiền về — xem MomoController::hoanTatSauThanhToan(). Nút này
     * vẫn giữ nguyên làm đường lui khi lần tạo tự động đó lỗi mạng.
     */
    public function createShipment(Order $order, GHNOrderService $ghnOrders): RedirectResponse
    {
        $ketQua = $ghnOrders->create($order);

        if (($ketQua['code'] ?? null) !== 200) {
            return back()->with('error', 'Không tạo được vận đơn GHN: '
                .($ketQua['message'] ?? 'không rõ lý do'));
        }

        $this->audit()->log(
            'order.shipment_created',
            sprintf('Tạo vận đơn GHN %s cho đơn %s',
                $ketQua['data']['order_code'] ?? '?', $order->order_number),
            $order,
            ['ghn_order_code' => $ketQua['data']['order_code'] ?? null],
        );

        return back()->with('success', ($ketQua['da_co_san'] ?? false)
            ? $ketQua['message']
            : 'Đã tạo vận đơn GHN: '.($ketQua['data']['order_code'] ?? ''));
    }

    /**
     * Huỷ vận đơn đã tạo.
     *
     * KHÔNG xoá mã vận đơn khỏi đơn hàng sau khi huỷ — mã đó là bằng
     * chứng cửa hàng đã từng bàn giao và đã huỷ. Xoá đi thì đơn trông
     * như chưa bao giờ gửi, và không đối soát được với hoá đơn GHN.
     */
    public function cancelShipment(Order $order, GHNOrderService $ghnOrders): RedirectResponse
    {
        $ketQua = $ghnOrders->cancel($order);

        if (($ketQua['code'] ?? null) !== 200) {
            return back()->with('error', 'Không huỷ được vận đơn GHN: '
                .($ketQua['message'] ?? 'không rõ lý do'));
        }

        $this->audit()->log(
            'order.shipment_cancelled',
            sprintf('Huỷ vận đơn GHN %s của đơn %s', $order->ghn_order_code, $order->order_number),
            $order,
        );

        return back()->with('success', 'Đã huỷ vận đơn GHN.');
    }
}