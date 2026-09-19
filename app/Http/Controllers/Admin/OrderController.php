<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Http\Controllers\Admin\Concerns\SortsAdminList;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShippingStatus;
use App\Services\Time\Gio;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
        $status = OrderStatus::tryFrom((string) $request->query('status'))?->value;
        $moiTrang = in_array((int) $request->query('moi_trang'), [20, 50, 100], true) ? (int) $request->query('moi_trang') : 20;
        $loc = $this->locChung($request);

        return view('admin.orders.index', [
            'orders' => (clone $loc)
                ->status($status)
                ->with(['items:id,order_id,product_name,quantity'])
                ->withCount('items')
                ->tap(fn ($q) => $this->applySort($q, $request, [
                    'ma' => 'order_number',
                    'tien' => 'grand_total',
                    'trang-thai' => 'status',
                    'thanh-toan' => 'payment_status',
                    'ngay' => 'created_at',
                ], fn ($q) => $q->latest()))
                ->paginate($moiTrang)
                ->withQueryString(),
            'currentStatus' => $status,
            'statuses' => OrderStatus::cases(),
            'counts' => (clone $loc)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all(),
            'phuongThuc' => PaymentMethod::cases(),
            'vanChuyen' => ShippingStatus::cases(),
        ]);
    }

    private function locChung(Request $request): Builder
    {
        return Order::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $tu = trim((string) $request->query('q'));
                $ma = preg_replace('/[^A-Za-z0-9]/', '', $tu) ?? '';
                $thich = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $tu) . '%';

                $query->where(function ($q) use ($thich, $ma) {
                    $q->where('recipient_phone', 'like', $thich)
                        ->orWhere('recipient_name', 'like', $thich)
                        ->orWhere('ghn_order_code', 'like', $thich)
                        ->orWhereHas('items', fn ($i) => $i->where('product_name', 'like', $thich));

                    if ($ma !== '') {
                        $q->orWhereRaw("REPLACE(order_number, '-', '') LIKE ?", ['%' . $ma . '%']);
                    }
                });
            })
            ->when(PaymentStatus::tryFrom((string) $request->query('payment')), fn ($q, $v) => $q->where('payment_status', $v->value))
            ->when(PaymentMethod::tryFrom((string) $request->query('phuong_thuc')), fn ($q, $v) => $q->where('payment_method', $v->value))
            ->when(ShippingStatus::tryFrom((string) $request->query('van_chuyen')), fn ($q, $v) => $q->where('shipping_status', $v->value))
            ->when($request->query('van_don') === 'cho-tao', fn ($q) => $q->awaitingWaybill())
            ->when($request->query('van_don') === 'roi', fn ($q) => $q->whereNotNull('ghn_order_code'))
            ->when($request->query('hoan_tien') === 'chua-ro', fn ($q) => $q->refundPending())
            ->when($this->ngay($request, 'tu_ngay'), fn ($q, $d) => $q->where('created_at', '>=', $d->copy()->startOfDay()->utc()))
            ->when($this->ngay($request, 'den_ngay'), fn ($q, $d) => $q->where('created_at', '<', $d->copy()->addDay()->startOfDay()->utc()));
    }

    private function ngay(Request $request, string $khoa): ?Carbon
    {
        $chuoi = (string) $request->query($khoa);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $chuoi)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $chuoi, Gio::mui());
        } catch (\Throwable) {
            return null;
        }
    }

    public function printSlip(Order $order): View
    {
        return view('admin.orders.print', [
            'order' => $order->load('items'),
        ]);
    }

    public function show(Order $order, RefundService $refunds, \App\Services\Exchange\ExchangeService $doiHang): View
    {
        $order->load('items.product', 'user', 'statusEvents.changedBy', 'invoice', 'transactions.installmentPayment', 'refunds.items.orderItem', 'refunds.createdBy', 'exchanges.items', 'installmentPlan.payments');

        return view('admin.orders.show', [
            'order' => $order,

            'exchangeBlocked' => $doiHang->lyDoKhongDoiDuoc($order),
            'exchangeable' => $order->items->mapWithKeys(fn ($item) => [
                $item->id => $doiHang->conDoiDuoc($item),
            ]),
            'exchangeWhyNot' => $order->items->mapWithKeys(fn ($item) => [
                $item->id => $doiHang->lyDoDongKhongDoiDuoc($item),
            ]),

            'hangDoiDuoc' => \App\Models\Product::query()
                ->where('status', 'active')
                ->where('product_type', '!=', \App\Enums\ProductType::Flower->value)
                ->orderBy('name')
                ->get(['id', 'name']),

            'refundBlocked' => $refunds->lyDoKhongHoanDuoc($order),
            'refundMethods' => $refunds->cachHoan($order),
            'refundReasons' => \App\Enums\RefundReason::choTrangThai($order->status),
            'returnable' => $order->items->mapWithKeys(fn ($item) => [
                $item->id => (int) $item->quantity - $refunds->soDaTra($item),
            ]),

            'owesRefund' => $this->orders->owesRefund($order),
            'paymentTargets' => $order->payment_method === \App\Enums\PaymentMethod::TraGop ? [] : array_values(array_filter(
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
        $hoanHang = $order->status === OrderStatus::Shipping
            && $request->input('status') === OrderStatus::Cancelled->value;

        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'cancel_reason' => [$hoanHang ? 'required' : 'nullable', 'string', 'max:255'],
        ], [], [
            'status' => 'trạng thái',
            'cancel_reason' => $hoanHang ? 'lý do hoàn hàng' : 'lý do huỷ',
        ]);

        try {
            $this->orders->changeStatus(
                $order,
                OrderStatus::from($validated['status']),
                $hoanHang ? 'Hoàn hàng: ' . $validated['cancel_reason'] : ($validated['cancel_reason'] ?? null),
                hoanHang: $hoanHang,
            );
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $hoanHang ? 'Đã ghi nhận hoàn hàng, tồn kho đã được cộng lại.' : 'Đã cập nhật trạng thái đơn hàng.');
    }

    public function updateDelivery(Request $request, Order $order): RedirectResponse
    {
        if (! self::suaDuocGiaoHang($order)) {
            return back()->with('error', 'Chỉ sửa thông tin giao hàng khi đơn chưa chuẩn bị và chưa có vận đơn GHN.');
        }

        $data = $request->validate([
            'recipient_name' => ['required', 'string', 'max:150'],
            'recipient_phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s().]{8,20}$/'],
            'shipping_address' => ['required', 'string', 'max:255'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'delivery_note' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'recipient_name' => 'người nhận',
            'recipient_phone' => 'số điện thoại',
            'shipping_address' => 'số nhà, đường',
            'delivery_date' => 'ngày giao',
            'delivery_note' => 'ghi chú',
        ]);

        $truoc = [];
        foreach (array_keys($data) as $o) {
            $cu = $order->getAttribute($o);
            $truoc[$o] = $cu instanceof \DateTimeInterface ? $cu->format('Y-m-d') : (string) $cu;
        }

        $order->forceFill($data)->save();

        $doi = [];
        foreach ($truoc as $o => $cu) {
            $moi = $order->getAttribute($o);
            $moi = $moi instanceof \DateTimeInterface ? $moi->format('Y-m-d') : (string) $moi;
            if ($cu !== $moi) {
                $doi[$o] = $cu . ' → ' . $moi;
            }
        }

        if ($doi !== []) {
            $this->audit()->log(
                'order.delivery_updated',
                sprintf('Sửa thông tin giao hàng của đơn %s', $order->order_number),
                $order,
                $doi,
            );
        }

        return back()->with('success', $doi === [] ? 'Không có gì thay đổi.' : 'Đã sửa thông tin giao hàng.');
    }

    public static function suaDuocGiaoHang(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::Pending, OrderStatus::Confirmed], true)
            && ! $order->ghn_order_code;
    }

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

    public function updateNote(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ], [
            'admin_note.max' => 'Ghi chú không được vượt quá 2000 ký tự.',
        ], ['admin_note' => 'ghi chú']);

        $order->admin_note = $data['admin_note'] ?: null;
        $order->save();

        return back()->with('success', 'Đã lưu ghi chú.');
    }

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