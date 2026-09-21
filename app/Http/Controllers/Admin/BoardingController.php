<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BoardingHandover;
use App\Enums\BoardingMode;
use App\Enums\BoardingPaymentMethod;
use App\Enums\BoardingStatus;
use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Http\Controllers\Controller;
use App\Models\BoardingBooking;
use App\Models\BoardingExtra;
use App\Models\BoardingRate;
use App\Models\BoardingWindow;
use App\Services\Boarding\BoardingService;
use App\Services\Shop\StoreProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    /** Lập phiếu tại quầy: chép từ phiếu giấy khách điền — cùng các ô với phiếu online. */
    public function create(): View
    {
        return view('admin.boarding.create', [
            'cacGia' => BoardingRate::query()->active()->get(),
            'cacDip' => BoardingWindow::query()->sapToi($this->dichVu->homNay())->get(),
            'homNay' => $this->dichVu->homNay(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'nhan_cay_ngay' => ['nullable', 'boolean'],
            'monthly_price' => ['nullable', 'numeric', 'min:1000', 'max:100000000'],
            'yearly_price' => ['nullable', 'numeric', 'min:1000', 'max:1000000000'],
            'boarding_rate_id' => ['required', 'integer', Rule::exists('boarding_rates', 'id')->where('is_active', true)],
            'mode' => ['required', Rule::enum(BoardingMode::class)],
            'drop_off_on' => ['required', 'date'],
            'months' => ['nullable', 'integer', 'min:1', 'max:60'],
            'years' => ['nullable', 'integer', 'min:1', 'max:5'],
            'return_on' => ['nullable', 'date'],
            'boarding_window_id' => ['nullable', 'integer'],
            'plant_name' => ['required', 'string', 'max:150'],
            'plant_note' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'repeat_yearly' => ['nullable', 'boolean'],
            'handover' => ['required', Rule::enum(BoardingHandover::class)],
            'contact_phone' => ['required', 'string', 'max:20', function (string $o, mixed $v, \Closure $loi) {
                if (! StoreProfile::laSoDienThoai(trim((string) $v))) {
                    $loi('Số điện thoại chưa đúng (8-15 chữ số).');
                }
            }],
            'address' => ['nullable', 'required_if:handover,' . BoardingHandover::CuaHangLay->value, 'string', 'max:255'],
            'customer_note' => ['nullable', 'string', 'max:500'],
        ], ['address.required_if' => 'Cửa hàng đến lấy cây thì cần địa chỉ.'], ['customer_name' => 'tên khách']);

        $p = $this->dichVu->taoTaiQuay($request->user(), $d, $request->file('photo'));
        $this->audit()->log('boarding.counter', 'Lập phiếu chăm hộ tại quầy ' . $p->code);

        return redirect()->route('admin.boarding.show', $p)->with('success', 'Đã lập phiếu ' . $p->code . '. In phiếu đưa khách giữ.');
    }

    public function print(BoardingBooking $booking): View
    {
        $booking->load(['rate', 'window', 'payments', 'extras', 'user:id,name']);

        return view('shop.boarding.print', ['phieu' => $booking, 'cacGia' => collect(), 'quayLai' => route('admin.boarding.show', $booking)]);
    }

    public function blank(): View
    {
        return view('shop.boarding.print', ['phieu' => null, 'cacGia' => BoardingRate::query()->active()->get(), 'quayLai' => route('admin.boarding.index')]);
    }

    public function show(BoardingBooking $booking): View
    {
        $booking->load(['user:id,name,email', 'rate', 'window', 'events.user:id,name', 'payments', 'extras', 'parent:id,code', 'product:id,name,slug']);

        return view('admin.boarding.show', [
            'phieu' => $booking,
            'homNay' => $this->dichVu->homNay(),
        ]);
    }

    public function confirm(Request $request, BoardingBooking $booking): RedirectResponse
    {
        $d = $request->validate([
            'drop_off_on' => ['required', 'date'],
            'monthly_price' => ['required', 'numeric', 'min:1000', 'max:100000000'],
            'yearly_price' => ['nullable', 'numeric', 'min:1000', 'max:1000000000'],
            'handover_fee' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'adjustment' => ['nullable', 'numeric', 'min:-100000000', 'max:100000000'],
            'adjustment_reason' => ['nullable', 'required_unless:adjustment,0,null', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['adjustment_reason.required_unless' => 'Có điều chỉnh giá thì ghi lý do để khách hiểu.'], ['monthly_price' => 'giá chốt mỗi tháng', 'yearly_price' => 'giá chốt mỗi năm']);

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

    public function extraPropose(Request $request, BoardingBooking $booking): RedirectResponse
    {
        $d = $request->validate([
            'viec' => ['required', 'string', 'max:200'],
            'gia' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'ghi_chu' => ['nullable', 'string', 'max:500'],
        ], [], ['viec' => 'việc cần làm', 'gia' => 'giá']);

        $this->dichVu->deXuat($booking, $request->user(), $d['viec'], bcadd((string) $d['gia'], '0', 2), $d['ghi_chu'] ?? null);

        return back()->with('success', 'Đã gửi đề xuất, chờ khách đồng ý.');
    }

    public function extraQuote(Request $request, BoardingBooking $booking, BoardingExtra $extra): RedirectResponse
    {
        $this->cuaPhieu($booking, $extra);

        $d = $request->validate([
            'gia' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'ghi_chu' => ['nullable', 'string', 'max:500'],
        ], [], ['gia' => 'giá']);

        $this->dichVu->baoGiaThem($extra, $request->user(), bcadd((string) $d['gia'], '0', 2), $d['ghi_chu'] ?? null);

        return back()->with('success', 'Đã báo giá cho khách.');
    }

    public function extraReject(Request $request, BoardingBooking $booking, BoardingExtra $extra): RedirectResponse
    {
        $this->cuaPhieu($booking, $extra);

        $d = $request->validate(['ly_do' => ['required', 'string', 'max:500']], [], ['ly_do' => 'lý do']);

        $this->dichVu->tuChoiThem($extra, $request->user(), $d['ly_do']);

        return back()->with('success', 'Đã báo khách cửa hàng không nhận việc này.');
    }

    /** Ghi hộ câu trả lời của khách (khách tại quầy, hoặc khách đồng ý qua điện thoại). */
    public function extraAnswer(Request $request, BoardingBooking $booking, BoardingExtra $extra): RedirectResponse
    {
        $this->cuaPhieu($booking, $extra);

        $this->dichVu->traLoiThem($extra, $request->user(), $request->boolean('dong_y'));

        return back()->with('success', 'Đã ghi câu trả lời của khách.');
    }

    public function extraDone(Request $request, BoardingBooking $booking, BoardingExtra $extra): RedirectResponse
    {
        $this->cuaPhieu($booking, $extra);

        $d = $request->validate([
            'ghi_chu' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $this->dichVu->xongThem($extra, $request->user(), $d['ghi_chu'] ?? null, $request->file('photo'));

        return back()->with('success', 'Đã ghi là làm xong, khách nhận được cập nhật.');
    }

    private function cuaPhieu(BoardingBooking $p, BoardingExtra $x): void
    {
        abort_unless((int) $x->boarding_booking_id === (int) $p->id, 404);
    }

    public function payment(Request $request, BoardingBooking $booking): RedirectResponse
    {
        $d = $request->validate([
            'amount' => ['required', 'numeric', 'not_in:0', 'min:-100000000', 'max:100000000'],
            'method' => ['required', Rule::in(array_map(fn ($m) => $m->value, BoardingPaymentMethod::ghiTay()))],
            'note' => ['nullable', 'string', 'max:200'],
        ], ['amount.not_in' => 'Nhập số tiền khác 0.'], ['amount' => 'số tiền', 'method' => 'cách trả']);

        abort_if(in_array($booking->status, [BoardingStatus::ChoDuyet, BoardingStatus::TuChoi], true), 422, 'Phiếu chưa xác nhận thì chưa ghi tiền.');

        $this->dichVu->ghiThu($booking, $request->user(), number_format((float) $d['amount'], 2, '.', ''), BoardingPaymentMethod::from($d['method']), $d['note'] ?? null);
        $this->audit()->log('boarding.payment', 'Ghi tiền phiếu chăm hộ ' . $booking->code, null, ['so_tien' => $d['amount']]);

        return back()->with('success', 'Đã ghi tiền.');
    }
}
