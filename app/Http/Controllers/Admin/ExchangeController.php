<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exchange;
use App\Models\Order;
use App\Services\Exchange\ExchangeException;
use App\Services\Exchange\ExchangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phiếu đổi hàng ở khu quản trị.
 * ============================================================
 * CONTROLLER MỎNG, DỊCH VỤ DÀY.
 *
 * Mọi luật — hạn 7 ngày, hoa tươi không đổi, ai chịu phí ship, giá lấy ở
 * đâu, kho cộng trừ lúc nào — nằm trong ExchangeService. Ở đây chỉ nhận
 * dữ liệu, gọi, và dịch lỗi thành câu cho admin đọc.
 *
 * Viết luật ở controller thì luật đó chỉ đúng khi đi qua biểu mẫu này;
 * một lệnh artisan hay một job sau này sẽ đi vòng qua nó.
 */
class ExchangeController extends Controller
{
    public function __construct(
        private readonly ExchangeService $doiHang,
    ) {
    }

    public function index(Request $request): View
    {
        $q = Exchange::query()
            ->with(['order', 'createdBy'])
            ->withCount('items')
            ->latest('id');

        if ($tt = $request->query('trang_thai')) {
            $q->where('status', $tt);
        }

        if ($tim = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w
                ->where('code', 'like', '%' . $tim . '%')
                ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', '%' . $tim . '%')));
        }

        return view('admin.exchanges.index', [
            'phieu' => $q->paginate(20)->withQueryString(),
            'trangThai' => \App\Enums\ExchangeStatus::cases(),
        ]);
    }

    public function show(Exchange $exchange): View
    {
        $exchange->load([
            'order',
            'createdBy',
            'refund',
            'items.orderItem',
            'items.product',
        ]);

        return view('admin.exchanges.show', ['phieu' => $exchange]);
    }

    public function store(Request $request, Order $order): RedirectResponse
    {
        /*
         * KIỂM DỮ LIỆU GỬI LÊN TRƯỚC, rồi mới tới luật nghiệp vụ.
         *
         * Hai tầng khác nhau: tầng này chặn thứ không phải số, không có
         * trong danh sách, vượt giới hạn kích thước. Tầng kia mới hỏi
         * "món này còn đổi được không".
         */
        $request->validate([
            'reason' => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\ExchangeReason::class)],
            'note' => ['nullable', 'string', 'max:1000'],

            'tra' => ['required', 'array', 'max:50'],
            'tra.*.quantity' => ['nullable', 'integer', 'min:0', 'max:9999'],

            'moi' => ['required', 'array', 'max:20'],
            'moi.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'moi.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'moi.*.quantity' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [], [
            'reason' => 'lý do đổi',
            'tra' => 'hàng khách trả về',
            'moi' => 'hàng gửi cho khách',
        ]);

        try {
            $phieu = $this->doiHang->tao($order, [
                'reason' => (string) $request->input('reason'),
                'note' => $request->input('note'),
                'tra' => (array) $request->input('tra', []),
                'moi' => array_values((array) $request->input('moi', [])),
            ]);
        } catch (ExchangeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.exchanges.show', $phieu)
            ->with('success', 'Đã lập phiếu đổi hàng ' . $phieu->code . '.');
    }

    public function nhanHang(Request $request, Exchange $exchange): RedirectResponse
    {
        $request->validate([
            'ban_lai' => ['nullable', 'array', 'max:50'],
        ]);

        try {
            $this->doiHang->daNhanHang($exchange, (array) $request->input('ban_lai', []));
        } catch (ExchangeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã ghi nhận hàng trả về.');
    }

    public function hoanTat(Request $request, Exchange $exchange): RedirectResponse
    {
        $request->validate([
            'da_thu' => ['nullable', 'integer', 'min:0', 'max:999999999'],
        ], [], ['da_thu' => 'số tiền đã thu']);

        try {
            $this->doiHang->hoanTat($exchange, (int) $request->input('da_thu', 0));
        } catch (ExchangeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Phiếu ' . $exchange->code . ' đã hoàn tất.');
    }

    public function huy(Request $request, Exchange $exchange): RedirectResponse
    {
        $request->validate([
            'ly_do' => ['required', 'string', 'max:250'],
        ], [], ['ly_do' => 'lý do huỷ']);

        try {
            $this->doiHang->huy($exchange, (string) $request->input('ly_do'));
        } catch (ExchangeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã huỷ phiếu ' . $exchange->code . '.');
    }
}
