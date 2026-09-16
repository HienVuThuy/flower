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
            'order' => $order->load('items', 'invoice', 'refunds', 'installmentPlan.payments'),
        ]);
    }

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

        $reason = trim((string) $request->validated('reason'));

        $reason = $reason === ''
            ? 'Khách tự huỷ.'
            : 'Khách tự huỷ: ' . $reason;

        try {
            $this->orders->changeStatus($order, OrderStatus::Cancelled, $reason);
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->mailer->notifyShopOfCancellation($order);

        Log::info('Khách tự huỷ đơn hàng.', [
            'order_number' => $order->order_number,
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Đã huỷ đơn hàng ' . $order->order_number . '. Cửa hàng sẽ không giao đơn này nữa.');
    }
}
