<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BoardingStatus;
use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Http\Controllers\Controller;
use App\Models\BoardingBooking;
use App\Services\Boarding\BoardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** CHĂM CÂY HỘ phía cửa hàng: duyệt, nhận cây, cập nhật tình trạng, trả cây, ghi tiền. */
class BoardingController extends Controller
{
    use LogsAdminActivity;

    public function __construct(
        private readonly BoardingService $dichVu,
    ) {
    }

    public function index(Request $request): View
    {
        $trangThai = BoardingStatus::tryFrom((string) $request->query('trang_thai', ''));
        $tim = trim((string) $request->query('q', ''));

        $phieu = BoardingBooking::query()
            ->with(['user:id,name', 'rate:id,name'])
            ->when($trangThai, fn ($q) => $q->where('status', $trangThai->value))
            ->when($tim !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', '%' . $tim . '%')
                ->orWhere('plant_name', 'like', '%' . $tim . '%')
                ->orWhere('contact_phone', 'like', '%' . $tim . '%')))
            ->orderByRaw('return_on is null, return_on')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.boarding.index', [
            'phieu' => $phieu,
            'dem' => BoardingBooking::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'trangThai' => $trangThai,
        ]);
    }

    public function show(BoardingBooking $booking): View
    {
        $booking->load(['user:id,name,email', 'rate', 'window', 'events.user:id,name', 'parent:id,code', 'product:id,name,slug']);

        return view('admin.boarding.show', [
            'phieu' => $booking,
            'homNay' => $this->dichVu->homNay(),
        ]);
    }

    public function confirm(Request $request, BoardingBooking $booking): RedirectResponse
    {
        $d = $request->validate([
            'drop_off_on' => ['required', 'date'],
            'handover_fee' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'adjustment' => ['nullable', 'numeric', 'min:-100000000', 'max:100000000'],
            'adjustment_reason' => ['nullable', 'required_unless:adjustment,0,null', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['adjustment_reason.required_unless' => 'Có điều chỉnh giá thì ghi lý do để khách hiểu.']);

        $this->dichVu->xacNhan($booking, $request->user(), $d);
        $this->audit()->log('boarding.confirmed', 'Xác nhận phiếu chăm hộ ' . $booking->code);

        return back()->with('success', 'Đã xác nhận. Khách nhận được thông báo.');
    }

    public function reject(Request $request, BoardingBooking $booking): RedirectResponse
    {
        $d = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'lý do']);

        $this->dichVu->tuChoi($booking, $request->user(), $d['reason']);
        $this->audit()->log('boarding.rejected', 'Từ chối phiếu chăm hộ ' . $booking->code, null, ['ly_do' => $d['reason']]);

        return back()->with('success', 'Đã từ chối phiếu.');
    }

    public function cancel(Request $request, BoardingBooking $booking): RedirectResponse
    {
        $d = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'lý do']);

        $this->dichVu->huy($booking, $request->user(), $d['reason']);
        $this->audit()->log('boarding.cancelled', 'Huỷ phiếu chăm hộ ' . $booking->code);

        return back()->with('success', 'Đã huỷ phiếu.');
    }

    public function receive(Request $request, BoardingBooking $booking): RedirectResponse
    {
        $d = $request->validate(['ngay' => ['required', 'date'], 'note' => ['nullable', 'string', 'max:500']]);

        $this->dichVu->nhanCay($booking, $request->user(), $d['ngay'], $d['note'] ?? null);

        return back()->with('success', 'Đã ghi nhận cây về cửa hàng.');
    }

    public function update(Request $request, BoardingBooking $booking): RedirectResponse
    {
        $d = $request->validate([
            'note' => ['nullable', 'required_without:photo', 'string', 'max:1000'],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], ['note.required_without' => 'Viết ghi chú hoặc chọn ảnh.']);

        $this->dichVu->capNhat($booking, $request->user(), $d['note'] ?? null, $request->file('photo'));

        return back()->with('success', 'Đã gửi cập nhật cho khách.');
    }

    public function returnPlant(Request $request, BoardingBooking $booking): RedirectResponse
    {
        $d = $request->validate(['ngay' => ['required', 'date']]);

        $kySau = $this->dichVu->traCay($booking, $request->user(), $d['ngay']);
        $this->audit()->log('boarding.returned', 'Trả cây phiếu ' . $booking->code);

        return back()->with('success', $kySau
            ? 'Đã trả cây. Kỳ sau đã mở: phiếu ' . $kySau->code . '.'
            : ($booking->fresh()->waiting_next_window ? 'Đã trả cây. Khách chọn lặp lại nhưng chưa có lịch dịp năm sau — thêm lịch ở tab "Lịch trả theo dịp".' : 'Đã trả cây.'));
    }

    public function payment(Request $request, BoardingBooking $booking): RedirectResponse
    {
        $d = $request->validate([
            'amount' => ['required', 'numeric', 'not_in:0', 'min:-100000000', 'max:100000000'],
            'note' => ['nullable', 'string', 'max:200'],
        ], ['amount.not_in' => 'Nhập số tiền khác 0.'], ['amount' => 'số tiền']);

        $this->dichVu->ghiThu($booking, $request->user(), number_format((float) $d['amount'], 2, '.', ''), $d['note'] ?? null);
        $this->audit()->log('boarding.payment', 'Ghi tiền phiếu chăm hộ ' . $booking->code, null, ['so_tien' => $d['amount']]);

        return back()->with('success', 'Đã ghi tiền.');
    }
}
