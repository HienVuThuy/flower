<?php

namespace App\Http\Controllers\Shop;

use App\Enums\OrderStatus;
use App\Http\Controllers\Concerns\AuthorizesOrderAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\OrderCancelRequest;
use App\Models\Order;
use App\Services\Order\OrderException;
use App\Services\Order\OrderMailer;
use App\Services\Order\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class OrderController extends Controller
{
    use AuthorizesOrderAccess;

    public function __construct(
        private readonly OrderService $orders,
        private readonly OrderMailer $mailer,
    ) {
    }

    /** Lịch sử đơn — chỉ dành cho khách đã đăng nhập. */
    public function index(): View
    {
        return view('shop.orders.index', [
            'orders' => Order::where('user_id', Auth::id())
                ->withCount('items')
                ->latest()
                ->paginate(10),
        ]);
    }

    public function show(Order $order): View
    {
        $this->authorizeOrderAccess($order);

        return view('shop.orders.show', [
            'order' => $order->load('items', 'invoice', 'refunds'),
        ]);
    }

    /**
     * Khách tự huỷ đơn.
     *
     * HAI CỬA phải qua, và cả hai đều cần thiết:
     *
     *  1. authorizeOrderAccess() — đúng người. Thiếu bước này thì đổi mã trên
     *     URL là huỷ được đơn của người lạ.
     *
     *  2. isCancellableByCustomer() — đúng lúc. Không dùng isCancellable()
     *     vì hàm đó rộng hơn, dành cho admin (xem ghi chú trong Order).
     *
     * Việc đổi trạng thái, hoàn kho và trả lại lượt mã giảm giá đều do
     * OrderService::changeStatus() lo, trong một transaction — ở đây
     * KHÔNG tự sửa cột status, nếu không sẽ có hai nơi cùng biết cách
     * huỷ đơn và sớm muộn lệch nhau.
     */
    public function cancel(OrderCancelRequest $request, Order $order): RedirectResponse
    {
        $this->authorizeOrderAccess($order);

        if (! $order->isCancellableByCustomer()) {
            return back()->with('error', sprintf(
                'Đơn đang ở trạng thái "%s" nên không tự huỷ được. '
                . 'Vui lòng liên hệ cửa hàng để được hỗ trợ.',
                $order->status->label(),
            ));
        }

        /*
         * Ghi rõ đây là khách tự huỷ. Admin đọc lý do trong trang quản trị
         * cần phân biệt được với đơn do chính cửa hàng huỷ — hai chuyện có
         * ý nghĩa kinh doanh hoàn toàn khác nhau.
         */
        $reason = trim((string) $request->validated('reason'));

        $reason = $reason === ''
            ? 'Khách tự huỷ.'
            : 'Khách tự huỷ: ' . $reason;

        try {
            $this->orders->changeStatus($order, OrderStatus::Cancelled, $reason);
        } catch (OrderException $e) {
            /*
             * Vào được tới đây nghĩa là trạng thái đã đổi giữa lúc khách
             * mở trang và lúc bấm nút — ví dụ cửa hàng vừa chuyển sang
             * "Đang chuẩn bị". Báo cho khách chứ không đổ trang lỗi.
             */
            return back()->with('error', $e->getMessage());
        }

        /*
         * Báo cho cửa hàng — chỉ ở ĐÂY, không đặt trong changeStatus().
         *
         * changeStatus() dùng chung cho cả admin huỷ lẫn khách huỷ. Đặt
         * vào đó thì cửa hàng tự huỷ cũng tự gửi thư báo cho chính mình.
         * Phân biệt "ai huỷ" chỉ có ở tầng controller.
         */
        $this->mailer->notifyShopOfCancellation($order);

        Log::info('Khách tự huỷ đơn hàng.', [
            'order_number' => $order->order_number,
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Đã huỷ đơn hàng ' . $order->order_number . '. Cửa hàng sẽ không giao đơn này nữa.');
    }
}
