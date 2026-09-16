<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RefundRequest;
use App\Models\Order;
use App\Models\Refund;
use App\Services\Refund\RefundException;
use App\Services\Refund\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Hoàn tiền cho khách. */
class RefundController extends Controller
{
    public function __construct(
        private readonly RefundService $refunds,
    ) {
    }

    public function index(\Illuminate\Http\Request $request): \Illuminate\View\View
    {
        $q = Refund::query();

        if ($tt = \App\Enums\RefundStatus::tryFrom((string) $request->query('trang_thai'))) {
            $q->where('status', $tt->value);
        }

        if ($pt = \App\Enums\RefundMethod::tryFrom((string) $request->query('phuong_thuc'))) {
            $q->where('method', $pt->value);
        }

        if ($tim = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w
                ->where('code', 'like', '%' . $tim . '%')
                ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', '%' . $tim . '%')));
        }

        $daHoan = '0.00';

        foreach ((clone $q)->where('status', \App\Enums\RefundStatus::Completed->value)->pluck('amount') as $tien) {
            $daHoan = bcadd($daHoan, (string) $tien, 2);
        }

        return view('admin.refunds.index', [
            'hoanTien' => (clone $q)
                ->with('order:id,order_number,recipient_name')
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'daHoan' => $daHoan,
            'chuaRo' => (clone $q)->where('status', \App\Enums\RefundStatus::Pending->value)->count(),
            'trangThai' => \App\Enums\RefundStatus::cases(),
            'phuongThuc' => \App\Enums\RefundMethod::cases(),
        ]);
    }

    public function store(RefundRequest $request, Order $order): RedirectResponse
    {
        try {
            $refund = $this->refunds->hoan($order, $request->validated());
        } catch (RefundException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return match ($refund->status) {
            RefundStatus::Completed => back()->with('success', 'Đã ghi nhận hoàn ' . $refund->code . '.'),

            RefundStatus::Failed => back()->with('error', sprintf(
                'MoMo không hoàn được (%s). Chưa có đồng nào được trả lại; số tiền vẫn còn hoàn được.',
                $refund->gateway_response['message'] ?? 'không rõ lý do',
            )),

            RefundStatus::Pending => back()->with('error',
                'Không nhận được trả lời từ MoMo nên CHƯA RÕ tiền đã đi hay chưa. '
                . 'Kiểm trên cổng MoMo với mã ' . $refund->code . ' rồi xác nhận kết quả bên dưới — đừng hoàn lại lần nữa.'),
        };
    }

    public function confirm(Request $request, Refund $refund): RedirectResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:100']], [], [
            'reference' => 'mã giao dịch',
        ]);

        try {
            $this->refunds->xacNhanDaHoan($refund, $data['reference']);
        } catch (RefundException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã xác nhận hoàn ' . $refund->code . '.');
    }

    public function fail(Refund $refund): RedirectResponse
    {
        try {
            $this->refunds->danhDauThatBai($refund);
        } catch (RefundException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã ghi ' . $refund->code . ' là không thành công. Số tiền đó hoàn lại được.');
    }
}
