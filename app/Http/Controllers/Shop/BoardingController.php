<?php

namespace App\Http\Controllers\Shop;

use App\Enums\BoardingHandover;
use App\Enums\BoardingMode;
use App\Enums\MomoFlow;
use App\Http\Controllers\Controller;
use App\Models\BoardingBooking;
use App\Models\BoardingRate;
use App\Models\BoardingWindow;
use App\Models\Product;
use App\Services\Boarding\BoardingService;
use App\Services\Payment\MomoGateway;
use App\Services\Payment\PaymentException;
use App\Services\Shop\Money;
use App\Services\Shop\StoreProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** CHĂM CÂY HỘ phía khách: xem bảng giá, báo giá, gửi yêu cầu, theo dõi phiếu. */
class BoardingController extends Controller
{
    public function __construct(
        private readonly BoardingService $dichVu,
    ) {
    }

    public function index(Request $request): View
    {
        $sanPham = $request->filled('san-pham')
            ? Product::query()->where('slug', (string) $request->query('san-pham'))->first()
            : null;

        $cacGia = BoardingRate::query()->active()->get();

        return view('shop.boarding.index', [
            'cacGia' => $cacGia,
            'cacDip' => BoardingWindow::query()->sapToi($this->dichVu->homNay())->get(),
            'sanPham' => $sanPham,
            'giaGoiY' => $sanPham?->care_info['difficulty'] ?? null,
            'homNay' => $this->dichVu->homNay(),
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        $bg = $this->dichVu->baoGia($request->validate($this->luatBaoGia()));

        return response()->json([
            'care_amount' => Money::format($bg['care_amount']),
            'months' => $bg['months'],
            'return_on' => $bg['return_on']?->format('d/m/Y'),
            'tam_tinh' => $bg['tam_tinh'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate($this->luatBaoGia() + [
            'plant_name' => ['required', 'string', 'max:150'],
            'plant_note' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'repeat_yearly' => ['nullable', 'boolean'],
            'handover' => ['required', Rule::enum(BoardingHandover::class)],
            'contact_phone' => ['required', 'string', 'max:20', function (string $o, mixed $v, \Closure $loi) {
                if (! StoreProfile::laSoDienThoai(trim((string) $v))) {
                    $loi('Số điện thoại chưa đúng (8-15 chữ số).');
                }
            }],
            'address' => ['nullable', 'required_if:handover,' . BoardingHandover::CuaHangLay->value, 'string', 'max:255'],
            'customer_note' => ['nullable', 'string', 'max:500'],
        ], [
            'address.required_if' => 'Cửa hàng đến lấy cây thì cần địa chỉ.',
            'photo.max' => 'Ảnh tối đa 4MB.',
        ]);

        $p = $this->dichVu->taoPhieu($request->user(), $d, $request->file('photo'));

        return redirect()->route('shop.boarding.show', $p)
            ->with('success', 'Đã gửi yêu cầu. Cửa hàng sẽ xác nhận và hẹn ngày nhận cây với bạn.');
    }

    public function mine(Request $request): View
    {
        return view('shop.boarding.mine', [
            'cacPhieu' => BoardingBooking::query()
                ->where('user_id', $request->user()->id)
                ->with('rate:id,name')
                ->latest('id')
                ->paginate(10),
        ]);
    }

    public function show(Request $request, BoardingBooking $booking): View
    {
        $this->cuaKhach($request, $booking);

        $booking->load(['rate', 'window', 'events', 'payments', 'parent:id,code']);

        return view('shop.boarding.show', [
            'phieu' => $booking,
            'homNay' => $this->dichVu->homNay(),
            'coMomo' => app(MomoGateway::class)->configured(),
        ]);
    }

    /** Phiếu trắng để in — không có thông tin khách, ai cũng in được để viết tay tại quầy. */
    public function blank(): View
    {
        return view('shop.boarding.print', [
            'phieu' => null,
            'cacGia' => BoardingRate::query()->active()->get(),
            'quayLai' => route('shop.boarding.index'),
        ]);
    }

    public function print(Request $request, BoardingBooking $booking): View
    {
        $this->cuaKhach($request, $booking);

        $booking->load(['rate', 'window', 'payments', 'user:id,name']);

        return view('shop.boarding.print', [
            'phieu' => $booking,
            'cacGia' => collect(),
            'quayLai' => route('shop.boarding.show', $booking),
        ]);
    }

    public function momo(Request $request, BoardingBooking $booking): RedirectResponse
    {
        $this->cuaKhach($request, $booking);

        try {
            return redirect()->away($this->dichVu->moMomo($booking, MomoFlow::tryFrom((string) $request->input('cach', ''))));
        } catch (PaymentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, BoardingBooking $booking): RedirectResponse
    {
        $this->cuaKhach($request, $booking);

        $this->dichVu->huy($booking, $request->user(), 'Khách tự huỷ.');

        return back()->with('success', 'Đã huỷ yêu cầu.');
    }

    public function early(Request $request, BoardingBooking $booking): RedirectResponse
    {
        $this->cuaKhach($request, $booking);

        $d = $request->validate(['ngay' => ['required', 'date']], [], ['ngay' => 'ngày nhận cây']);

        $this->dichVu->nhanSom($booking, $request->user(), $d['ngay']);

        return back()->with('success', 'Đã hẹn ngày nhận cây. Cửa hàng sẽ chuẩn bị cây cho bạn.');
    }

    public function repeat(Request $request, BoardingBooking $booking): RedirectResponse
    {
        $this->cuaKhach($request, $booking);

        $this->dichVu->doiLapLai($booking, $request->boolean('bat'));

        return back()->with('success', $request->boolean('bat') ? 'Đã bật lặp lại mỗi năm.' : 'Đã tắt lặp lại.');
    }

    private function luatBaoGia(): array
    {
        return [
            'boarding_rate_id' => ['required', 'integer', Rule::exists('boarding_rates', 'id')->where('is_active', true)],
            'mode' => ['required', Rule::enum(BoardingMode::class)],
            'drop_off_on' => ['required', 'date'],
            'months' => ['nullable', 'integer', 'min:1', 'max:60'],
            'years' => ['nullable', 'integer', 'min:1', 'max:5'],
            'return_on' => ['nullable', 'date'],
            'boarding_window_id' => ['nullable', 'integer'],
        ];
    }

    private function cuaKhach(Request $request, BoardingBooking $p): void
    {
        abort_unless((int) $p->user_id === (int) $request->user()->id, 404);
    }
}
